<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Assets\Application\UseCases\CheckAssetAlertsUseCase;
use Illuminate\Console\Command;

class CheckAssetAlertsCommand extends Command
{
    protected $signature = 'sga:check-asset-alerts';

    protected $description = 'Notifica mantenimientos de activos vencidos/próximos y garantías o certificados por vencer';

    public function handle(CheckAssetAlertsUseCase $useCase): int
    {
        $result = $useCase->execute();

        $this->info("Mantenimientos vencidos: {$result['overdue']}");
        $this->info("Mantenimientos próximos: {$result['upcoming']}");
        $this->info("Garantías por vencer: {$result['warranties']}");
        $this->info("Certificados por vencer: {$result['certificates']}");

        return self::SUCCESS;
    }
}
