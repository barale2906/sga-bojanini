<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\CalibrationCertificateData;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Carga un certificado de calibración para un equipo de monitoreo.
 *
 * El nuevo certificado queda como vigente; el que estaba vigente pasa al
 * historial (no se borra) con la fecha en que fue reemplazado.
 */
class RegisterCalibrationCertificateUseCase
{
    public const DISK = 'monitoreo';

    public function __construct(
        private readonly SensorRepositoryInterface $sensors,
    ) {}

    public function execute(int $sensorId, CalibrationCertificateData $data, ?int $userId): SensorCalibrationCertificateModel
    {
        if ($this->sensors->findById($sensorId) === null) {
            throw new \DomainException("El sensor con ID {$sensorId} no existe.");
        }

        $path = $data->file->store("sensors/{$sensorId}", self::DISK);

        if ($path === false) {
            throw new \RuntimeException('No fue posible guardar el certificado.');
        }

        try {
            return DB::transaction(function () use ($sensorId, $data, $userId, $path) {
                SensorCalibrationCertificateModel::where('sensor_id', $sensorId)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (SensorCalibrationCertificateModel $previous) => $previous->update([
                        'is_current'  => false,
                        'replaced_at' => now(),
                    ]));

                return SensorCalibrationCertificateModel::create([
                    'sensor_id'          => $sensorId,
                    'certificate_number' => $data->certificateNumber,
                    'laboratory'         => $data->laboratory,
                    'calibrated_at'      => $data->calibratedAt,
                    'expires_at'         => $data->expiresAt,
                    'file_path'          => $path,
                    'original_name'      => $data->file->getClientOriginalName(),
                    'mime_type'          => (string) $data->file->getMimeType(),
                    'size'               => (int) $data->file->getSize(),
                    'notes'              => $data->notes,
                    'is_current'         => true,
                    'uploaded_by'        => $userId,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);

            throw $e;
        }
    }
}
