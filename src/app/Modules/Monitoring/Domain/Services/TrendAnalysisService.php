<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Services;

use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use Carbon\Carbon;

/**
 * Servicio que detecta tendencias en las lecturas de un sensor.
 *
 * Utiliza dos algoritmos complementarios:
 * 1. Regla de las 7 consecutivas (detección rápida)
 * 2. Regresión lineal (detección estadística rigurosa)
 *
 * Si ambos coinciden en que hay tendencia, la confianza es alta.
 */
class TrendAnalysisService
{
    public function __construct(
        private readonly SensorReadingRepositoryInterface $readingRepository,
    ) {}

    /**
     * Analiza las lecturas de un sensor para detectar tendencias.
     *
     * @param int    $sensorId ID del sensor a analizar
     * @param Carbon $dateFrom Fecha de inicio del análisis
     * @param Carbon $dateTo   Fecha de fin del análisis
     *
     * @return array{
     *     sensor_id: int,
     *     total_readings: int,
     *     consecutive_rule: array{
     *         has_trend: bool,
     *         direction: string|null,
     *         longest_streak: int,
     *         streak_start: string|null,
     *         streak_end: string|null
     *     },
     *     linear_regression: array{
     *         has_trend: bool,
     *         direction: string|null,
     *         slope: float,
     *         intercept: float,
     *         r_squared: float,
     *         interpretation: string
     *     },
     *     overall_trend: bool,
     *     overall_direction: string|null,
     *     confidence: string
     * }
     */
    public function analyzeTrend(int $sensorId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $readings = $this->readingRepository->findBySensorAndDateRange(
            $sensorId,
            $dateFrom,
            $dateTo,
        );

        $values = array_map(fn ($r) => $r->getValue(), $readings);
        $count = count($values);

        // Se necesitan al menos 7 lecturas para el análisis de consecutivas
        if ($count < 7) {
            return $this->buildEmptyResult($sensorId, $count);
        }

        // ──────────────────────────────────────────────────────
        // ALGORITMO 1: Regla de las 7 consecutivas
        // ──────────────────────────────────────────────────────
        $consecutiveResult = $this->analyzeConsecutiveRule($readings);

        // ──────────────────────────────────────────────────────
        // ALGORITMO 2: Regresión lineal
        // ──────────────────────────────────────────────────────
        $regressionResult = $this->analyzeLinearRegression($values);

        // ──────────────────────────────────────────────────────
        // Combinar resultados de ambos algoritmos
        // ──────────────────────────────────────────────────────
        $overallTrend = $consecutiveResult['has_trend'] || $regressionResult['has_trend'];
        $overallDirection = null;
        $confidence = 'none';

        if ($consecutiveResult['has_trend'] && $regressionResult['has_trend']) {
            // Ambos algoritmos detectan tendencia → alta confianza
            $overallDirection = $regressionResult['direction'];
            $confidence = 'high';
        } elseif ($regressionResult['has_trend']) {
            // Solo regresión lineal detecta tendencia → confianza media
            $overallDirection = $regressionResult['direction'];
            $confidence = 'medium';
        } elseif ($consecutiveResult['has_trend']) {
            // Solo la regla de 7 consecutivas → confianza baja
            $overallDirection = $consecutiveResult['direction'];
            $confidence = 'low';
        }

        return [
            'sensor_id'         => $sensorId,
            'total_readings'    => $count,
            'consecutive_rule'  => $consecutiveResult,
            'linear_regression' => $regressionResult,
            'overall_trend'     => $overallTrend,
            'overall_direction' => $overallDirection,
            'confidence'        => $confidence,
        ];
    }

    /**
     * ALGORITMO 1: Regla de las 7 consecutivas.
     *
     * ¿CÓMO FUNCIONA?
     * Recorre las lecturas una por una. Si encuentra 7 o más lecturas
     * donde CADA UNA es mayor que la anterior (o CADA UNA es menor),
     * entonces hay una tendencia.
     *
     * ¿POR QUÉ 7?
     * Es un estándar de la industria (Western Electric Rules).
     * La probabilidad de que 7 valores consecutivos suban por azar es
     * de solo (0.5)^6 = 1.56%, muy baja para ser casualidad.
     *
     * Ejemplo: [4.1, 4.2, 4.3, 4.5, 4.6, 4.7, 4.9] → tendencia UP
     * Ejemplo: [4.1, 3.9, 4.2, 3.8, 4.0, 3.7, 4.1] → sin tendencia
     *
     * @param array $readings Array de SensorReading ordenados por fecha
     */
    private function analyzeConsecutiveRule(array $readings): array
    {
        $values = array_map(fn ($r) => $r->getValue(), $readings);
        $count = count($values);

        // Variables para rastrear la racha más larga
        $longestStreak = 1;
        $longestDirection = null;
        $longestStartIdx = 0;
        $longestEndIdx = 0;

        // Variables para la racha actual
        $currentStreak = 1;
        $currentDirection = null;
        $currentStartIdx = 0;

        for ($i = 1; $i < $count; $i++) {
            // Determinar la dirección del movimiento actual
            if ($values[$i] > $values[$i - 1]) {
                $direction = 'up';
            } elseif ($values[$i] < $values[$i - 1]) {
                $direction = 'down';
            } else {
                // Valores iguales rompen la racha
                $currentStreak = 1;
                $currentDirection = null;
                $currentStartIdx = $i;
                continue;
            }

            // Si la dirección es la misma que la anterior, extender racha
            if ($direction === $currentDirection) {
                $currentStreak++;
            } else {
                // Cambió la dirección, reiniciar racha
                $currentStreak = 2; // El punto anterior + el actual
                $currentDirection = $direction;
                $currentStartIdx = $i - 1;
            }

            // Actualizar la racha más larga encontrada
            if ($currentStreak > $longestStreak) {
                $longestStreak = $currentStreak;
                $longestDirection = $currentDirection;
                $longestStartIdx = $currentStartIdx;
                $longestEndIdx = $i;
            }
        }

        $hasTrend = $longestStreak >= 7;

        return [
            'has_trend'      => $hasTrend,
            'direction'      => $hasTrend ? $longestDirection : null,
            'longest_streak' => $longestStreak,
            'streak_start'   => $hasTrend
                ? $readings[$longestStartIdx]->getRecordedAt()->format('Y-m-d H:i:s')
                : null,
            'streak_end'     => $hasTrend
                ? $readings[$longestEndIdx]->getRecordedAt()->format('Y-m-d H:i:s')
                : null,
        ];
    }

    /**
     * ALGORITMO 2: Regresión lineal.
     *
     * ¿QUÉ ES LA REGRESIÓN LINEAL?
     * Calcula la línea recta que mejor se ajusta a los datos.
     * La ecuación de la línea es: y = mx + b
     *   - m (pendiente): cuánto sube o baja la línea por cada unidad de x
     *   - b (intercepto): valor de y cuando x = 0
     *
     * Si m > 0 → la línea sube → tendencia al alza
     * Si m < 0 → la línea baja → tendencia a la baja
     * Si m ≈ 0 → la línea es plana → sin tendencia
     *
     * ¿QUÉ ES R²?
     * Es un número entre 0 y 1 que mide cuánto de la variación de los
     * datos se explica por la tendencia lineal:
     * - R² = 1.0 → todos los puntos están EXACTAMENTE en la línea
     * - R² = 0.7 → el 70% de la variación se explica por la línea (bueno)
     * - R² = 0.1 → solo el 10% se explica por la línea (sin tendencia clara)
     *
     * Usamos R² > 0.7 como umbral para decir "hay tendencia".
     *
     * FÓRMULAS:
     * x = posición de la lectura (0, 1, 2, 3, ...)
     * y = valor de la lectura
     *
     * Pendiente (m):
     *   m = (n × Σxy - Σx × Σy) / (n × Σx² - (Σx)²)
     *
     * Intercepto (b):
     *   b = (Σy - m × Σx) / n
     *
     * Coeficiente de determinación (R²):
     *   R² = (n × Σxy - Σx × Σy)² / ((n × Σx² - (Σx)²) × (n × Σy² - (Σy)²))
     *
     * @param float[] $values Array de valores numéricos de las lecturas
     */
    private function analyzeLinearRegression(array $values): array
    {
        $n = count($values);

        // Inicializar las sumatorias que necesitamos para las fórmulas
        $sumX  = 0.0;  // Σx
        $sumY  = 0.0;  // Σy
        $sumXY = 0.0;  // Σ(x × y)
        $sumX2 = 0.0;  // Σ(x²)
        $sumY2 = 0.0;  // Σ(y²)

        // Recorrer cada lectura y acumular las sumatorias
        // x = posición (0, 1, 2, ..., n-1)
        // y = valor de la lectura
        for ($i = 0; $i < $n; $i++) {
            $x = (float) $i;
            $y = $values[$i];

            $sumX  += $x;          // Ejemplo: 0+1+2+3+4 = 10
            $sumY  += $y;          // Ejemplo: 4.1+4.2+4.3+4.5+4.6 = 21.7
            $sumXY += ($x * $y);   // Ejemplo: 0×4.1 + 1×4.2 + 2×4.3 + ...
            $sumX2 += ($x * $x);   // Ejemplo: 0²+1²+2²+3²+4² = 30
            $sumY2 += ($y * $y);   // Ejemplo: 4.1²+4.2²+...
        }

        // Calcular el denominador de la fórmula de la pendiente
        // denominador = n × Σx² - (Σx)²
        $denominator = ($n * $sumX2) - ($sumX * $sumX);

        // Si el denominador es 0, todos los x son iguales (imposible aquí,
        // pero lo protegemos por seguridad)
        if (abs($denominator) < 0.0001) {
            return $this->buildEmptyRegression();
        }

        // ── Calcular pendiente (m) ──
        // m = (n × Σxy - Σx × Σy) / (n × Σx² - (Σx)²)
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

        // ── Calcular intercepto (b) ──
        // b = (Σy - m × Σx) / n
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        // ── Calcular R² ──
        // R² = (n × Σxy - Σx × Σy)² / ((n × Σx² - (Σx)²) × (n × Σy² - (Σy)²))
        $numeratorR2 = ($n * $sumXY) - ($sumX * $sumY);
        $denomY = ($n * $sumY2) - ($sumY * $sumY);

        // Proteger contra denominador 0 (todos los valores y son iguales)
        if (abs($denomY) < 0.0001 || abs($denominator) < 0.0001) {
            $rSquared = 0.0;
        } else {
            $rSquared = ($numeratorR2 * $numeratorR2) / ($denominator * $denomY);
        }

        // Asegurar que R² está entre 0 y 1 (puede salir ligeramente fuera
        // por errores de punto flotante)
        $rSquared = max(0.0, min(1.0, $rSquared));

        // ── Determinar si hay tendencia ──
        // Criterio: R² > 0.7 (el 70% de la variación es explicada por la tendencia)
        $hasTrend = $rSquared > 0.7;
        $direction = null;

        if ($hasTrend) {
            $direction = $slope > 0 ? 'up' : 'down';
        }

        // Generar interpretación legible
        $interpretation = $this->interpretRegression($slope, $rSquared, $hasTrend, $n);

        return [
            'has_trend'      => $hasTrend,
            'direction'      => $direction,
            'slope'          => round($slope, 6),
            'intercept'      => round($intercept, 4),
            'r_squared'      => round($rSquared, 4),
            'interpretation' => $interpretation,
        ];
    }

    /**
     * Genera una interpretación legible del resultado de la regresión.
     */
    private function interpretRegression(
        float $slope,
        float $rSquared,
        bool $hasTrend,
        int $n,
    ): string {
        if (!$hasTrend) {
            return "No se detecta tendencia significativa (R²={$rSquared}, pendiente={$slope}, n={$n}).";
        }

        $direction = $slope > 0 ? 'ascendente' : 'descendente';
        $rPct = round($rSquared * 100, 1);
        $slopeFormatted = round(abs($slope), 4);

        return "Tendencia {$direction} detectada: cambio de {$slopeFormatted} unidades/lectura, "
            . "R²={$rSquared} (el {$rPct}% de la variación es explicada por la tendencia). "
            . "Basado en {$n} lecturas.";
    }

    private function buildEmptyResult(int $sensorId, int $count): array
    {
        return [
            'sensor_id'         => $sensorId,
            'total_readings'    => $count,
            'consecutive_rule'  => [
                'has_trend'      => false,
                'direction'      => null,
                'longest_streak' => 0,
                'streak_start'   => null,
                'streak_end'     => null,
            ],
            'linear_regression' => $this->buildEmptyRegression(),
            'overall_trend'     => false,
            'overall_direction' => null,
            'confidence'        => 'none',
        ];
    }

    private function buildEmptyRegression(): array
    {
        return [
            'has_trend'      => false,
            'direction'      => null,
            'slope'          => 0.0,
            'intercept'      => 0.0,
            'r_squared'      => 0.0,
            'interpretation' => 'Datos insuficientes para calcular regresión lineal.',
        ];
    }
}
