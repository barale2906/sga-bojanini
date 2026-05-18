<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Services;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

/**
 * Servicio que evalúa si una nueva lectura de sensor viola alguna regla
 * de alerta o está fuera del rango permitido por la zona.
 *
 * Se invoca cada vez que se registra una lectura nueva (manual o IoT).
 *
 * FLUJO:
 * 1. Recibir la lectura nueva
 * 2. Obtener la zona del sensor para saber los rangos permitidos
 * 3. Comparar la lectura con los rangos de la zona
 * 4. Evaluar cada regla de alerta activa del sensor
 * 5. Si hay violación, retornar las alertas a generar
 */
class ConditionAlertService
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly SensorReadingRepositoryInterface $readingRepository,
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    /**
     * Evalúa una lectura recién registrada y retorna las alertas generadas.
     *
     * @param SensorReading $reading La lectura nueva a evaluar
     *
     * @return array Lista de alertas. Cada alerta es un array con:
     *   - type: string        Tipo de alerta (out_of_range, above, below)
     *   - severity: string    Nivel (critical, warning)
     *   - message: string     Mensaje descriptivo
     *   - rule_id: int|null   ID de la regla que se violó (null si es por rango de zona)
     *   - channels: array     Canales de notificación (internal, email, push)
     */
    public function evaluateReading(SensorReading $reading): array
    {
        $alerts = [];

        // ── PASO 1: Obtener datos del sensor y la zona ──
        $sensor = $this->sensorRepository->findById($reading->getSensorId());
        if ($sensor === null) {
            return $alerts;
        }

        $zone = $this->zoneRepository->findById($sensor->getZoneId());
        if ($zone === null) {
            return $alerts;
        }

        // ── PASO 2: Verificar contra los rangos de la zona ──
        // Cada zona tiene rangos permitidos de temperatura y humedad:
        //   zona refrigerada: temp_min=2, temp_max=8, humidity_min=30, humidity_max=65
        //   zona ambiente: temp_min=15, temp_max=30, humidity_min=30, humidity_max=70
        $value = $reading->getValue();
        $sensorType = $sensor->getType();

        if ($sensorType === 'temperature') {
            $min = $zone->getTempMin();
            $max = $zone->getTempMax();
        } else {
            $min = $zone->getHumidityMin();
            $max = $zone->getHumidityMax();
        }

        // Si los límites de la zona están definidos, verificar
        if ($min !== null && $max !== null) {
            if ($value < $min) {
                $alerts[] = [
                    'type'     => 'out_of_range',
                    'severity' => 'critical',
                    'message'  => sprintf(
                        'Sensor %s: lectura %.2f %s está POR DEBAJO del mínimo permitido (%.2f %s) en zona "%s".',
                        $sensor->getCode(),
                        $value,
                        $sensor->getUnit(),
                        $min,
                        $sensor->getUnit(),
                        $zone->getName(),
                    ),
                    'rule_id'  => null,
                    'channels' => ['internal', 'email', 'push'],
                ];
            } elseif ($value > $max) {
                $alerts[] = [
                    'type'     => 'out_of_range',
                    'severity' => 'critical',
                    'message'  => sprintf(
                        'Sensor %s: lectura %.2f %s está POR ENCIMA del máximo permitido (%.2f %s) en zona "%s".',
                        $sensor->getCode(),
                        $value,
                        $sensor->getUnit(),
                        $max,
                        $sensor->getUnit(),
                        $zone->getName(),
                    ),
                    'rule_id'  => null,
                    'channels' => ['internal', 'email', 'push'],
                ];
            }
        }

        // ── PASO 3: Evaluar reglas de alerta activas del sensor ──
        $rules = $this->alertRuleRepository->findActiveBySensorId($sensor->getId());

        foreach ($rules as $rule) {
            if ($rule->getConditionType() === 'out_of_range') {
                // Ya se evaluó arriba con los rangos de la zona
                continue;
            }

            if ($rule->getConditionType() === 'trend_up' || $rule->getConditionType() === 'trend_down') {
                // Las tendencias se evalúan con el comando programado (sga:analyze-conditions)
                continue;
            }

            // Para reglas above/below: verificar si la lectura actual viola la regla
            if (!$rule->isViolatedBy($value)) {
                continue;
            }

            // Si la regla requiere N lecturas consecutivas, verificar las últimas N
            $requiredConsecutive = $rule->getConsecutiveReadings();

            if ($requiredConsecutive > 1) {
                $recentReadings = $this->readingRepository->findLastNBySensor(
                    $sensor->getId(),
                    $requiredConsecutive,
                );

                // ¿Todas las últimas N lecturas violan la regla?
                $allViolate = count($recentReadings) >= $requiredConsecutive;
                foreach ($recentReadings as $recent) {
                    if (!$rule->isViolatedBy($recent->getValue())) {
                        $allViolate = false;
                        break;
                    }
                }

                if (!$allViolate) {
                    continue;
                }
            }

            $directionText = $rule->getConditionType() === 'above' ? 'SUPERA' : 'ESTÁ POR DEBAJO de';

            $alerts[] = [
                'type'     => $rule->getConditionType(),
                'severity' => 'warning',
                'message'  => sprintf(
                    'Sensor %s: lectura %.2f %s %s el umbral de %.2f (%d lecturas consecutivas).',
                    $sensor->getCode(),
                    $value,
                    $sensor->getUnit(),
                    $directionText,
                    $rule->getThreshold(),
                    $requiredConsecutive,
                ),
                'rule_id'  => $rule->getId(),
                'channels' => $rule->getNotificationChannels(),
            ];
        }

        return $alerts;
    }
}
