<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Shared\Infrastructure\Persistence\Models\ReportExportModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los archivos temporales de reportes generados en background una
 * vez expiraron, junto con su registro de seguimiento. Evita que los
 * reportes pesados queden almacenados indefinidamente en el servidor.
 *
 * Se ejecuta diariamente a las 02:00 vía Task Scheduler.
 *
 * Uso manual: php artisan sga:cleanup-report-exports
 */
class CleanupExpiredReportExportsCommand extends Command
{
    protected $signature = 'sga:cleanup-report-exports';
    protected $description = 'Elimina archivos y registros de reportes exportados que ya expiraron (diario 02:00)';

    public function handle(): int
    {
        $expired = ReportExportModel::where('expires_at', '<', now())->get();

        foreach ($expired as $export) {
            if ($export->file_path !== null) {
                Storage::disk($export->file_disk)->delete($export->file_path);
            }

            $export->delete();
        }

        $this->info("Eliminados {$expired->count()} reportes expirados.");

        return self::SUCCESS;
    }
}
