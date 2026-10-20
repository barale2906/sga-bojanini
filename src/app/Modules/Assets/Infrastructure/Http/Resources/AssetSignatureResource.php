<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Firmante de un documento de activos. La imagen de la firma no se expone
 * en JSON: solo se imprime en el acta PDF.
 */
class AssetSignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'role'            => $this->role,
            'method'          => $this->method,
            'signer_name'     => $this->signer_name,
            'signer_document' => $this->signer_document,
            'signed_at'       => $this->signed_at?->toIso8601String(),
        ];
    }
}
