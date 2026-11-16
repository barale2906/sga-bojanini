<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Monitoring\Application\UseCases\CheckSensorCalibrationsUseCase;
use Illuminate\Console\Command;

class CheckSensorCalibrationsCommand extends Command
{
    protected $signature = 'sga:check-sensor-calibrations';

    protected $description = 'Notifica certificados de calibración de equipos de monitoreo por vencer o vencidos';

    public function handle(CheckSensorCalibrationsUseCase $useCase): int
    {
        $result = $useCase->execute();

        $this->info("Certificados por vencer notificados: {$result['expiring']}");
        $this->info("Certificados vencidos notificados: {$result['expired']}");
        $this->info("Usuarios notificados: {$result['notified_users']}");

        return self::SUCCESS;
    }
}
