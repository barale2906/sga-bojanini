<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use Illuminate\Support\Facades\DB;

/**
 * Retira un certificado cargado por error (borrado lógico: el archivo se
 * conserva). Si era el vigente, el más reciente del historial vuelve a
 * quedar como vigente.
 */
class DeleteCalibrationCertificateUseCase
{
    public function execute(SensorCalibrationCertificateModel $certificate): void
    {
        DB::transaction(function () use ($certificate) {
            $wasCurrent = $certificate->is_current;

            $certificate->update(['is_current' => false]);
            $certificate->delete();

            if (! $wasCurrent) {
                return;
            }

            $previous = SensorCalibrationCertificateModel::where('sensor_id', $certificate->sensor_id)
                ->orderByDesc('expires_at')
                ->orderByDesc('id')
                ->first();

            $previous?->update(['is_current' => true, 'replaced_at' => null]);
        });
    }
}
