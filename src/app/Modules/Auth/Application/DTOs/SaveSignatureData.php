<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTOs;

use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;

/**
 * Datos para registrar o actualizar la firma del usuario autenticado.
 */
final readonly class SaveSignatureData
{
    public function __construct(
        public IdentityDocumentType $documentType,
        public string $documentNumber,
        public string $signatureData,
    ) {}
}
