<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\StatisticalControlService;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use Carbon\Carbon;

/**
 * Caso de uso: Obtener el gráfico de control estadístico de un sensor.
 *
 * El usuario selecciona un sensor y un rango de fechas, y el sistema
 * calcula la media, desviación estándar, límites de control, Cp/Cpk
 * y marca las lecturas que están fuera de control.
 */
class GetControlChartUseCase
{
    public function __construct(
        private readonly StatisticalControlService $statisticalService,
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    public function execute(int $sensorId, string $dateFrom, string $dateTo): array
    {
        $sensor = $this->sensorRepository->findById($sensorId);
        if ($sensor === null) {
            throw new \DomainException("El sensor con ID {$sensorId} no existe.");
        }

        $zone = $this->zoneRepository->findById($sensor->getZoneId());
        if ($zone === null) {
            throw new \DomainException("La zona del sensor no existe.");
        }

        // Obtener los límites de la zona según el tipo de sensor
        if ($sensor->getType() === 'temperature') {
            $zoneMin = $zone->getTempMin() ?? 0.0;
            $zoneMax = $zone->getTempMax() ?? 100.0;
        } else {
            $zoneMin = $zone->getHumidityMin() ?? 0.0;
            $zoneMax = $zone->getHumidityMax() ?? 100.0;
        }

        return $this->statisticalService->calculateControlChart(
            $sensorId,
            Carbon::parse($dateFrom),
            Carbon::parse($dateTo),
            $zoneMin,
            $zoneMax,
        );
    }
}
