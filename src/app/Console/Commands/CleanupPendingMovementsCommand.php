<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Console\Command;

/**
 * Elimina movimientos que llevan más de 30 días sin ser confirmados.
 * Un movimiento en estado 'pending_signature' por ese tiempo se considera
 * abandonado y no debería permanecer pendiente indefinidamente.
 *
 * Se ejecuta diariamente a las 03:00 vía Task Scheduler.
 *
 * Uso manual: php artisan sga:cleanup-pending-movements
 */
class CleanupPendingMovementsCommand extends Command
{
    protected $signature = 'sga:cleanup-pending-movements';
    protected $description = 'Elimina movimientos pendientes de firma con más de 30 días sin confirmar (diario 03:00)';

    public function handle(): int
    {
        $cutoff = now()->subDays(30);

        $deleted = StockMovementModel::where('status', 'pending_signature')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Eliminados {$deleted} movimientos pendientes de firma con más de 30 días.");

        return self::SUCCESS;
    }
}
