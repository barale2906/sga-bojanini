<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\ConditionAlertService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso: Registrar múltiples lecturas de sensores de un dispositivo IoT.
 *
 * Los dispositivos IoT envían lecturas en lotes (cada 5 o 15 minutos).
 * Este caso de uso procesa el lote completo en una transacción,
 * identifica los sensores por su código y evalúa alertas para cada lectura.
 */
class RegisterBulkReadingsUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly SensorReadingRepositoryInterface $readingRepository,
        private readonly ConditionAlertService $alertService,
    ) {}

    /**
     * @param array $readingsData Array de arrays con:
     *   - sensor_code: string  Código del sensor (ej: "TEMP-ZR01-01")
     *   - value: float         Valor de la lectura
     *   - recorded_at: string  Fecha ISO 8601
     *
     * @return array{saved: int, errors: array, alerts: array}
     */
    public function execute(array $readingsData): array
    {
        $saved = 0;
        $errors = [];
        $allAlerts = [];

        DB::beginTransaction();

        try {
            foreach ($readingsData as $index => $item) {
                // Buscar el sensor por código
                $sensor = $this->sensorRepository->findByCode($item['sensor_code']);

                if ($sensor === null) {
                    $errors[] = [
                        'index'   => $index,
                        'code'    => $item['sensor_code'],
                        'message' => "Sensor con código '{$item['sensor_code']}' no encontrado.",
                    ];
                    continue;
                }

                if (!$sensor->isActive()) {
                    $errors[] = [
                        'index'   => $index,
                        'code'    => $item['sensor_code'],
                        'message' => "Sensor '{$item['sensor_code']}' está inactivo.",
                    ];
                    continue;
                }

                $reading = new SensorReading(
                    id: null,
                    sensorId: $sensor->getId(),
                    value: (float) $item['value'],
                    readingSource: 'iot',
                    recordedAt: new DateTimeImmutable($item['recorded_at']),
                    userId: null,
                );

                $reading = $this->readingRepository->save($reading);
                $saved++;

                // Evaluar alertas para esta lectura
                $alerts = $this->alertService->evaluateReading($reading);
                if (!empty($alerts)) {
                    $allAlerts = array_merge($allAlerts, $alerts);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'saved'  => $saved,
            'errors' => $errors,
            'alerts' => $allAlerts,
        ];
    }
}
