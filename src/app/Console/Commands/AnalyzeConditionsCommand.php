<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\TrendAnalysisService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando que analiza las tendencias de todos los sensores activos.
 *
 * Se ejecuta cada 30 minutos vía el Task Scheduler de Laravel.
 * Si detecta una tendencia, genera una notificación.
 *
 * Uso manual: php artisan sga:analyze-conditions
 */
class AnalyzeConditionsCommand extends Command
{
    protected $signature = 'sga:analyze-conditions';
    protected $description = 'Analiza tendencias en las lecturas de sensores activos (cada 30 min)';

    public function handle(
        SensorRepositoryInterface $sensorRepository,
        TrendAnalysisService $trendService,
    ): int {
        $sensors = $sensorRepository->findAllActive();
        $count = count($sensors);

        $this->info("Analizando {$count} sensores activos...");

        $alertsGenerated = 0;

        foreach ($sensors as $sensor) {
            $result = $trendService->analyzeTrend(
                $sensor->getId(),
                Carbon::now()->subHours(24),
                Carbon::now(),
            );

            if ($result['overall_trend']) {
                $direction = $result['overall_direction'] === 'up' ? 'ascendente' : 'descendente';
                $this->warn("  ⚠ Sensor {$sensor->getCode()}: tendencia {$direction} (confianza: {$result['confidence']})");

                // Aquí se generaría la notificación ConditionTrendAlertNotification
                // (se implementa en Fase 9)
                $alertsGenerated++;

                Log::warning("Tendencia detectada en sensor {$sensor->getCode()}", $result);
            } else {
                $this->line("  ✓ Sensor {$sensor->getCode()}: sin tendencia");
            }
        }

        $this->info("Análisis completado. Alertas generadas: {$alertsGenerated}");

        return self::SUCCESS;
    }
}
