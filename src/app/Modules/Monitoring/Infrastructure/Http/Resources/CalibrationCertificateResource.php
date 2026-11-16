<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use App\Modules\Monitoring\Domain\Services\CalibrationStatusService;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SensorCalibrationCertificateModel */
class CalibrationCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusService = app(CalibrationStatusService::class);
        $status = $this->is_current
            ? $statusService->status($this->expires_at, now())
            : 'replaced';

        return [
            'id'                 => $this->id,
            'sensor_id'          => $this->sensor_id,
            'certificate_number' => $this->certificate_number,
            'laboratory'         => $this->laboratory,
            'calibrated_at'      => $this->calibrated_at?->toDateString(),
            'expires_at'         => $this->expires_at->toDateString(),
            'days_left'          => $statusService->daysLeft($this->expires_at, now()),
            'is_current'         => $this->is_current,
            'status'             => $status,
            'status_label'       => $status === 'replaced' ? 'Reemplazado' : $statusService->label($status),
            'replaced_at'        => $this->replaced_at?->format('Y-m-d H:i:s'),
            'original_name'      => $this->original_name,
            'mime_type'          => $this->mime_type,
            'size'               => $this->size,
            'notes'              => $this->notes,
            'uploaded_by'        => $this->whenLoaded('uploader', fn () => $this->uploader ? [
                'id'   => $this->uploader->id,
                'name' => $this->uploader->name,
            ] : null),
            'created_at'         => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
