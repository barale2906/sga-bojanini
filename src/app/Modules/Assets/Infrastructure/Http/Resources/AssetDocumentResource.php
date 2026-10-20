<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'asset_id'            => $this->asset_id,
            'maintenance_id'      => $this->maintenance_id,
            'document_type'       => $this->document_type->value,
            'document_type_label' => $this->document_type->label(),
            'title'               => $this->title,
            'original_name'       => $this->original_name,
            'mime_type'           => $this->mime_type,
            'size'                => $this->size,
            'issued_at'           => $this->issued_at?->toDateString(),
            'expires_at'          => $this->expires_at?->toDateString(),
            'uploaded_by'         => $this->relationLoaded('uploader') ? UserSummary::of($this->uploader) : null,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
