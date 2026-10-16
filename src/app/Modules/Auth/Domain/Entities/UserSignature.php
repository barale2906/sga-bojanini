<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entities;

use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use DateTimeImmutable;

/**
 * Versión activa (o histórica) de la firma registrada de un usuario.
 *
 * Nunca expone el PIN ni su HMAC: solo indica si el usuario tiene uno.
 */
class UserSignature
{
    public function __construct(
        private int $id,
        private int $userId,
        private string $signerName,
        private IdentityDocumentType $documentType,
        private string $documentNumber,
        private string $signatureData,
        private bool $hasPin,
        private ?DateTimeImmutable $pinGeneratedAt,
        private DateTimeImmutable $registeredAt,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    /** Nombre del usuario dueño de la firma (se copia al comprobante). */
    public function getSignerName(): string
    {
        return $this->signerName;
    }

    public function getDocumentType(): IdentityDocumentType
    {
        return $this->documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    /** Imagen de la firma como data URL (data:image/png;base64,...). */
    public function getSignatureData(): string
    {
        return $this->signatureData;
    }

    public function hasPin(): bool
    {
        return $this->hasPin;
    }

    public function getPinGeneratedAt(): ?DateTimeImmutable
    {
        return $this->pinGeneratedAt;
    }

    public function getRegisteredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
