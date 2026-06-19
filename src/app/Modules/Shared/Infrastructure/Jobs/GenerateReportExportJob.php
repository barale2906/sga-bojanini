<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Jobs;

use App\Modules\Shared\Application\Services\ReportExportService;
use App\Modules\Shared\Domain\Enums\ReportExportStatus;
use App\Modules\Shared\Infrastructure\Notifications\ReportFailedNotification;
use App\Modules\Shared\Infrastructure\Notifications\ReportReadyNotification;
use App\Modules\Shared\Infrastructure\Persistence\Models\ReportExportModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Genera en background un reporte solicitado desde el módulo de Reportes
 * cuando su volumen de datos es demasiado grande para responderse de
 * forma síncrona. El archivo resultante se guarda temporalmente (se borra
 * automáticamente al expirar, ver sga:cleanup-report-exports) y el
 * usuario solicitante recibe una notificación con el enlace de descarga.
 */
class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [30, 90];

    public function __construct(
        private readonly string $exportId,
    ) {}

    public function handle(ReportExportService $exportService): void
    {
        $export = ReportExportModel::findOrFail($this->exportId);
        $export->update(['status' => ReportExportStatus::Processing->value]);

        try {
            $file = $exportService->generate($export->type, $export->filters ?? [], $export->format);

            $disk = (string) config('sga.reports.disk', 'local');
            $directory = (string) config('sga.reports.directory', 'reports');
            $relativePath = "{$directory}/{$export->id}.{$file->extension}";

            Storage::disk($disk)->put($relativePath, $file->content);

            $export->update([
                'status'       => ReportExportStatus::Ready->value,
                'file_path'    => $relativePath,
                'file_disk'    => $disk,
                'file_size'    => $file->sizeInBytes(),
                'completed_at' => now(),
                'expires_at'   => now()->addDays((int) config('sga.reports.retention_days', 7)),
            ]);

            $export->user?->notify(new ReportReadyNotification($export));
        } catch (\Throwable $e) {
            Log::error("Error generando reporte en background ({$export->type}, export {$export->id}): {$e->getMessage()}");

            $export->update([
                'status'        => ReportExportStatus::Failed->value,
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);

            $export->user?->notify(new ReportFailedNotification($export));

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $export = ReportExportModel::find($this->exportId);

        if ($export === null || $export->status === ReportExportStatus::Failed->value) {
            return;
        }

        $export->update([
            'status'        => ReportExportStatus::Failed->value,
            'error_message' => $e->getMessage(),
            'completed_at'  => now(),
        ]);

        $export->user?->notify(new ReportFailedNotification($export));
    }
}
