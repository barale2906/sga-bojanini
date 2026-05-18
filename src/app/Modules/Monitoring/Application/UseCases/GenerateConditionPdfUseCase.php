<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\StatisticalControlService;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Caso de uso: Generar un reporte PDF de condiciones de almacenamiento.
 *
 * Genera un documento PDF con:
 * - Encabezado con datos del sensor y zona
 * - Tabla de todas las lecturas en el periodo
 * - Estadísticas (media, σ, límites)
 * - Lecturas fuera de rango marcadas en rojo
 */
class GenerateConditionPdfUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly SensorReadingRepositoryInterface $readingRepository,
        private readonly StatisticalControlService $statisticalService,
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    /**
     * @param int    $sensorId ID del sensor
     * @param string $dateFrom Fecha inicio (Y-m-d)
     * @param string $dateTo   Fecha fin (Y-m-d)
     * @return string Ruta absoluta del PDF generado
     */
    public function execute(int $sensorId, string $dateFrom, string $dateTo): string
    {
        $sensor = $this->sensorRepository->findById($sensorId);
        if ($sensor === null) {
            throw new \DomainException("Sensor no encontrado.");
        }

        $zone = $this->zoneRepository->findById($sensor->getZoneId());

        if ($sensor->getType() === 'temperature') {
            $zoneMin = $zone ? $zone->getTempMin() ?? 0 : 0;
            $zoneMax = $zone ? $zone->getTempMax() ?? 100 : 100;
        } else {
            $zoneMin = $zone ? $zone->getHumidityMin() ?? 0 : 0;
            $zoneMax = $zone ? $zone->getHumidityMax() ?? 100 : 100;
        }

        $from = Carbon::parse($dateFrom);
        $to   = Carbon::parse($dateTo);

        $readings = $this->readingRepository->findBySensorAndDateRange($sensorId, $from, $to);
        $chart    = $this->statisticalService->calculateControlChart($sensorId, $from, $to, $zoneMin, $zoneMax);

        $pdf = Pdf::loadView('reports.conditions', [
            'sensor'    => $sensor,
            'zone'      => $zone,
            'readings'  => $readings,
            'chart'     => $chart,
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
            'zoneMin'   => $zoneMin,
            'zoneMax'   => $zoneMax,
            'generated' => now()->format('Y-m-d H:i:s'),
        ]);

        $fileName = sprintf(
            'condiciones_%s_%s_%s.pdf',
            $sensor->getCode(),
            $from->format('Ymd'),
            $to->format('Ymd'),
        );
        $directory = storage_path('app/exports');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = "{$directory}/{$fileName}";

        $pdf->save($path);

        return $path;
    }
}
