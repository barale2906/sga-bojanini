<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Monitoring\Application\UseCases\GenerateConditionPdfUseCase;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Comando que genera reportes PDF diarios de condiciones de almacenamiento
 * para todos los sensores activos.
 *
 * Se ejecuta diariamente a las 23:00 vía Task Scheduler.
 *
 * Uso manual: php artisan sga:generate-condition-reports
 */
class GenerateConditionReportsCommand extends Command
{
    protected $signature = 'sga:generate-condition-reports';
    protected $description = 'Genera PDFs diarios de condiciones de almacenamiento (diario 23:00)';

    public function handle(
        SensorRepositoryInterface $sensorRepository,
        GenerateConditionPdfUseCase $pdfUseCase,
    ): int {
        $sensors = $sensorRepository->findAllActive();
        $today   = Carbon::today()->toDateString();

        $count = count($sensors);
        $this->info("Generando reportes para {$count} sensores...");

        foreach ($sensors as $sensor) {
            try {
                $path = $pdfUseCase->execute(
                    $sensor->getId(),
                    $today,
                    $today,
                );
                $this->line("  ✓ {$sensor->getCode()}: {$path}");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$sensor->getCode()}: {$e->getMessage()}");
            }
        }

        $this->info('Generación de reportes completada.');

        return self::SUCCESS;
    }
}
