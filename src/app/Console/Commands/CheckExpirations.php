<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Inventory\Application\UseCases\CheckExpirationAlertsUseCase;
use Illuminate\Console\Command;

class CheckExpirations extends Command
{
    protected $signature = 'sga:check-expirations';

    protected $description = 'Verifica lotes próximos a vencer y marca los vencidos';

    public function handle(CheckExpirationAlertsUseCase $useCase): int
    {
        $result = $useCase->execute();

        $this->info("Lotes próximos a vencer: {$result['expiring']}");
        $this->info("Lotes marcados como vencidos: {$result['expired']}");

        return self::SUCCESS;
    }
}
