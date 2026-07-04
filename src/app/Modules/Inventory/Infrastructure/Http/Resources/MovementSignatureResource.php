<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementSignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'role'            => $this->role,
            'signer_name'     => $this->signer_name,
            'signer_document' => $this->signer_document,
            'signed_at'       => $this->signed_at?->toIso8601String(),
        ];
    }
}
