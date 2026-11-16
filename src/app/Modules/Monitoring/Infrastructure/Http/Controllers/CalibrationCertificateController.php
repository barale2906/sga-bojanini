<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Monitoring\Application\UseCases\DeleteCalibrationCertificateUseCase;
use App\Modules\Monitoring\Application\UseCases\RegisterCalibrationCertificateUseCase;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreCalibrationCertificateRequest;
use App\Modules\Monitoring\Infrastructure\Http\Resources\CalibrationCertificateResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificados de calibración de los equipos de monitoreo.
 *
 *   GET    /api/v1/sensors/{sensorId}/calibrations   → historial (vigente primero)
 *   POST   /api/v1/sensors/{sensorId}/calibrations   → cargar (multipart)
 *   GET    /api/v1/calibrations/{id}/download        → descargar archivo
 *   DELETE /api/v1/calibrations/{id}                 → retirar (cargado por error)
 */
class CalibrationCertificateController extends Controller
{
    use ApiResponse;
    use ChecksSensorAccess;

    public function index(int $sensorId, Request $request): JsonResponse
    {
        SensorModel::findOrFail($sensorId);
        $this->assertSensorAccess($request->user(), $sensorId);

        $certificates = SensorCalibrationCertificateModel::with('uploader:id,name')
            ->where('sensor_id', $sensorId)
            ->orderByDesc('is_current')
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->get();

        return $this->success(CalibrationCertificateResource::collection($certificates), 'Certificados de calibración');
    }

    public function store(
        int $sensorId,
        StoreCalibrationCertificateRequest $request,
        RegisterCalibrationCertificateUseCase $useCase,
    ): JsonResponse {
        $this->assertSensorAccess($request->user(), $sensorId);

        $certificate = $useCase->execute($sensorId, $request->toData(), (int) $request->user()->id);

        return $this->created(
            new CalibrationCertificateResource($certificate->load('uploader:id,name')),
            'Certificado de calibración cargado',
        );
    }

    public function download(int $id, Request $request): StreamedResponse
    {
        $certificate = SensorCalibrationCertificateModel::findOrFail($id);
        $this->assertSensorAccess($request->user(), $certificate->sensor_id);

        $disk = Storage::disk(RegisterCalibrationCertificateUseCase::DISK);
        abort_unless($disk->exists($certificate->file_path), 404, 'El archivo del certificado no existe.');

        return $disk->download($certificate->file_path, $certificate->original_name, [
            'Content-Type' => $certificate->mime_type,
        ]);
    }

    public function destroy(int $id, Request $request, DeleteCalibrationCertificateUseCase $useCase): JsonResponse
    {
        $certificate = SensorCalibrationCertificateModel::findOrFail($id);
        $this->assertSensorAccess($request->user(), $certificate->sensor_id);

        $useCase->execute($certificate);

        return $this->noContent('Certificado retirado');
    }
}
