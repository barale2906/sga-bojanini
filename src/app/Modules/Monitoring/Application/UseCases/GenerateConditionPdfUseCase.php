<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\StatisticalControlService;
use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
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
 *
 * No escribe a disco: produce el PDF en memoria. Quien lo invoque decide
 * qué hacer con él (descarga inmediata o archivado, ver
 * ConditionReportController::generate() y GenerateConditionReportsCommand).
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
     */
    public function execute(int $sensorId, string $dateFrom, string $dateTo): GeneratedReportFile
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

        $reportName = sprintf('condiciones_%s', $sensor->getCode());

        return GeneratedReportFile::make($reportName, 'pdf', 'application/pdf', $pdf->output());
    }
}
