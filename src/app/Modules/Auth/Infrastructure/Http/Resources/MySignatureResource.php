<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Resources;

use App\Modules\Auth\Domain\Entities\UserSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Firma registrada del usuario autenticado. Nunca incluye el PIN ni su HMAC.
 *
 * @mixin UserSignature
 */
class MySignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var UserSignature $signature */
        $signature = $this->resource;

        return [
            'signer_name'         => $signature->getSignerName(),
            'document_type'       => $signature->getDocumentType()->value,
            'document_type_label' => $signature->getDocumentType()->label(),
            'document_number'     => $signature->getDocumentNumber(),
            'signature_data'      => $signature->getSignatureData(),
            'has_pin'             => $signature->hasPin(),
            'pin_generated_at'    => $signature->getPinGeneratedAt()?->format(DATE_ATOM),
            'registered_at'       => $signature->getRegisteredAt()->format(DATE_ATOM),
        ];
    }
}
