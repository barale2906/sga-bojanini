<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Services;

use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use Carbon\Carbon;

/**
 * Servicio que implementa el control estadístico de procesos (SPC).
 *
 * Calcula gráficos de control para verificar que las condiciones
 * de almacenamiento (temperatura, humedad) se mantienen estables
 * y dentro de los límites aceptables.
 *
 * CONCEPTO CLAVE: Un gráfico de control es una herramienta estadística
 * que muestra si un proceso está "bajo control" (estable) o si hay
 * problemas que requieren intervención.
 */
class StatisticalControlService
{
    public function __construct(
        private readonly SensorReadingRepositoryInterface $readingRepository,
    ) {}

    /**
     * Calcula el gráfico de control para un sensor en un rango de fechas.
     *
     * ¿QUÉ ES UN GRÁFICO DE CONTROL?
     * Es una herramienta estadística que muestra si un proceso está "bajo control".
     * Tiene una línea central (media) y dos pares de límites:
     * - UCL (Upper Control Limit) = media + 3 × desviación estándar
     * - LCL (Lower Control Limit) = media - 3 × desviación estándar
     * Si una lectura sale de estos límites, hay un problema.
     *
     * PASO 1: Obtener lecturas del sensor en el rango de fechas
     * PASO 2: Calcular la media (promedio) = suma de valores / cantidad
     * PASO 3: Calcular la desviación estándar (σ) = raíz cuadrada de la varianza
     *         varianza = suma de (valor - media)² / (cantidad - 1)
     * PASO 4: Calcular límites
     *         UCL = media + 3σ    (Límite de control superior)
     *         LCL = media - 3σ    (Límite de control inferior)
     *         UWL = media + 2σ    (Límite de advertencia superior)
     *         LWL = media - 2σ    (Límite de advertencia inferior)
     * PASO 5: Calcular Cp y Cpk (capacidad del proceso)
     *         Cp = (limite_zona_max - limite_zona_min) / (6σ)
     *         Cpk = min(
     *             (limite_zona_max - media) / (3σ),
     *             (media - limite_zona_min) / (3σ)
     *         )
     *         Cp ≥ 1.33 = proceso capaz
     *         Cpk ≥ 1.33 = proceso capaz Y centrado
     *
     * @param int    $sensorId    ID del sensor a analizar
     * @param Carbon $dateFrom    Fecha de inicio del análisis
     * @param Carbon $dateTo      Fecha de fin del análisis
     * @param float  $zoneMin     Límite mínimo permitido por la zona (ej: 2.0 °C)
     * @param float  $zoneMax     Límite máximo permitido por la zona (ej: 8.0 °C)
     *
     * @return array{
     *     sensor_id: int,
     *     date_from: string,
     *     date_to: string,
     *     total_readings: int,
     *     mean: float,
     *     std_dev: float,
     *     ucl: float,
     *     lcl: float,
     *     uwl: float,
     *     lwl: float,
     *     cp: float|null,
     *     cpk: float|null,
     *     process_capable: bool,
     *     out_of_control_count: int,
     *     out_of_control_readings: array,
     *     readings: array
     * }
     */
    public function calculateControlChart(
        int $sensorId,
        Carbon $dateFrom,
        Carbon $dateTo,
        float $zoneMin,
        float $zoneMax,
    ): array {
        // ──────────────────────────────────────────────────────
        // PASO 1: Obtener las lecturas del sensor en el rango
        // ──────────────────────────────────────────────────────
        // El repositorio retorna un array de objetos SensorReading
        // ordenados por fecha (recorded_at ASC).
        $readings = $this->readingRepository->findBySensorAndDateRange(
            $sensorId,
            $dateFrom,
            $dateTo,
        );

        // Extraer solo los valores numéricos para los cálculos
        // Ejemplo: [4.2, 4.5, 4.1, 4.8, 3.9, ...]
        $values = array_map(
            fn ($reading) => $reading->getValue(),
            $readings,
        );

        $count = count($values);

        // Si hay menos de 2 lecturas, no se puede calcular desviación estándar
        if ($count < 2) {
            return [
                'sensor_id'              => $sensorId,
                'date_from'              => $dateFrom->toIso8601String(),
                'date_to'                => $dateTo->toIso8601String(),
                'total_readings'         => $count,
                'mean'                   => $count === 1 ? $values[0] : 0,
                'std_dev'                => 0,
                'ucl'                    => 0,
                'lcl'                    => 0,
                'uwl'                    => 0,
                'lwl'                    => 0,
                'cp'                     => null,
                'cpk'                    => null,
                'process_capable'        => false,
                'out_of_control_count'   => 0,
                'out_of_control_readings' => [],
                'readings'               => [],
            ];
        }

        // ──────────────────────────────────────────────────────
        // PASO 2: Calcular la media (promedio)
        // ──────────────────────────────────────────────────────
        // Fórmula: media = suma de todos los valores / cantidad de valores
        // Ejemplo: (4.2 + 4.5 + 4.1 + 4.8 + 3.9) / 5 = 4.3
        $sum = array_sum($values);
        $mean = $sum / $count;

        // ──────────────────────────────────────────────────────
        // PASO 3: Calcular la desviación estándar (σ)
        // ──────────────────────────────────────────────────────
        // La desviación estándar mide cuánto se dispersan los datos
        // respecto a la media. Si σ es pequeña, los datos están
        // concentrados (bueno). Si es grande, hay mucha variación (malo).
        //
        // Fórmula paso a paso:
        //   1. Para cada valor, restar la media: (valor - media)
        //   2. Elevar al cuadrado: (valor - media)²
        //   3. Sumar todos los cuadrados
        //   4. Dividir por (cantidad - 1)  ← esto es la VARIANZA
        //      (Se divide por n-1 en vez de n porque es una "muestra",
        //       no la "población completa". Esto se llama "corrección de Bessel".)
        //   5. Sacar raíz cuadrada de la varianza ← esto es σ

        // Paso 3.1 y 3.2: Calcular la suma de cuadrados de las diferencias
        $sumSquaredDiffs = 0.0;
        foreach ($values as $value) {
            $diff = $value - $mean;          // Ejemplo: 4.2 - 4.3 = -0.1
            $sumSquaredDiffs += $diff * $diff; // Ejemplo: (-0.1)² = 0.01
        }

        // Paso 3.3: Calcular la varianza (dividir por n-1)
        $variance = $sumSquaredDiffs / ($count - 1);

        // Paso 3.4: La desviación estándar es la raíz cuadrada de la varianza
        $stdDev = sqrt($variance);

        // ──────────────────────────────────────────────────────
        // PASO 4: Calcular los límites de control y advertencia
        // ──────────────────────────────────────────────────────
        // Los límites se calculan como múltiplos de la desviación estándar:
        //
        // Límites de CONTROL (±3σ): Si un punto sale de aquí, es una ALARMA.
        //   UCL = media + 3 × σ    (límite superior de control)
        //   LCL = media - 3 × σ    (límite inferior de control)
        //
        // Límites de ADVERTENCIA (±2σ): Si un punto sale de aquí, es una ALERTA.
        //   UWL = media + 2 × σ    (límite superior de advertencia)
        //   LWL = media - 2 × σ    (límite inferior de advertencia)
        //
        // ¿Por qué 3σ? Porque estadísticamente, el 99.73% de los datos deben
        // estar dentro de ±3σ si el proceso es estable. Si un punto sale,
        // la probabilidad de que sea "normal" es solo del 0.27%.

        $ucl = $mean + (3 * $stdDev);
        $lcl = $mean - (3 * $stdDev);
        $uwl = $mean + (2 * $stdDev);
        $lwl = $mean - (2 * $stdDev);

        // ──────────────────────────────────────────────────────
        // PASO 5: Calcular Cp y Cpk (capacidad del proceso)
        // ──────────────────────────────────────────────────────
        // Cp mide si el proceso ES CAPAZ de mantenerse dentro de los límites
        // de la zona (ej: temperatura entre 2°C y 8°C).
        //
        // Cpk mide si el proceso ESTÁ CENTRADO dentro de esos límites.
        //
        // Ejemplo visual:
        //   Zona: [2°C ──────── 8°C]
        //   Proceso centrado en 5°C con σ=0.5:   Cp=2.0, Cpk=2.0  ✓
        //   Proceso centrado en 7°C con σ=0.5:   Cp=2.0, Cpk=0.67 ✗
        //   (El segundo es capaz pero NO está centrado)

        $cp = null;
        $cpk = null;
        $processCapable = false;

        if ($stdDev > 0) {
            // Cp = ancho_de_la_zona / (6 × σ)
            // Si la zona es [2, 8], el ancho es 8-2 = 6.
            // Si σ = 0.5, entonces Cp = 6 / (6 × 0.5) = 6/3 = 2.0
            // Un Cp ≥ 1.33 significa que el proceso tiene suficiente "holgura".
            $cp = ($zoneMax - $zoneMin) / (6 * $stdDev);

            // Cpk = el menor de dos valores:
            //   (limite_max - media) / (3σ)  → cuánta holgura hay arriba
            //   (media - limite_min) / (3σ)  → cuánta holgura hay abajo
            // Si el proceso está descentrado hacia un lado, Cpk será menor que Cp.
            $cpk = min(
                ($zoneMax - $mean) / (3 * $stdDev),
                ($mean - $zoneMin) / (3 * $stdDev),
            );

            // El proceso se considera "capaz" si AMBOS son ≥ 1.33
            $processCapable = ($cp >= 1.33) && ($cpk >= 1.33);
        }

        // ──────────────────────────────────────────────────────
        // PASO 6: Identificar lecturas fuera de control
        // ──────────────────────────────────────────────────────
        $outOfControl = [];
        foreach ($readings as $reading) {
            $val = $reading->getValue();
            if ($val > $ucl || $val < $lcl) {
                $outOfControl[] = [
                    'id'          => $reading->getId(),
                    'value'       => $val,
                    'recorded_at' => $reading->getRecordedAt()->format('Y-m-d H:i:s'),
                    'deviation'   => round(abs($val - $mean) / ($stdDev ?: 1), 2),
                ];
            }
        }

        // Preparar lecturas para el gráfico (con indicadores)
        $chartReadings = array_map(fn ($reading) => [
            'id'          => $reading->getId(),
            'value'       => $reading->getValue(),
            'recorded_at' => $reading->getRecordedAt()->format('Y-m-d H:i:s'),
            'status'      => $this->classifyReading($reading->getValue(), $ucl, $lcl, $uwl, $lwl),
        ], $readings);

        return [
            'sensor_id'               => $sensorId,
            'date_from'               => $dateFrom->toIso8601String(),
            'date_to'                 => $dateTo->toIso8601String(),
            'total_readings'          => $count,
            'mean'                    => round($mean, 4),
            'std_dev'                 => round($stdDev, 4),
            'ucl'                     => round($ucl, 4),
            'lcl'                     => round($lcl, 4),
            'uwl'                     => round($uwl, 4),
            'lwl'                     => round($lwl, 4),
            'cp'                      => $cp !== null ? round($cp, 4) : null,
            'cpk'                     => $cpk !== null ? round($cpk, 4) : null,
            'process_capable'         => $processCapable,
            'out_of_control_count'    => count($outOfControl),
            'out_of_control_readings' => $outOfControl,
            'readings'                => $chartReadings,
        ];
    }

    /**
     * Clasifica una lectura según en qué zona del gráfico de control cae.
     *
     * Zonas (de mejor a peor):
     * - "ok"      → dentro de ±2σ (zona verde)
     * - "warning" → entre ±2σ y ±3σ (zona amarilla)
     * - "alarm"   → fuera de ±3σ (zona roja)
     */
    private function classifyReading(
        float $value,
        float $ucl,
        float $lcl,
        float $uwl,
        float $lwl,
    ): string {
        if ($value > $ucl || $value < $lcl) {
            return 'alarm';
        }
        if ($value > $uwl || $value < $lwl) {
            return 'warning';
        }
        return 'ok';
    }
}
