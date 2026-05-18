<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Entities;

use DateTimeImmutable;

/**
 * Entidad de dominio que representa un registro de auditoría.
 */
class AuditLog
{
    public function __construct(
        private ?int $id,
        private ?int $userId,
        private string $action,
        private string $auditableType,
        private ?int $auditableId,
        private ?array $oldValues,
        private ?array $newValues,
        private ?string $ipAddress,
        private ?string $userAgent,
        private DateTimeImmutable $createdAt,
        private ?string $userName = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getAuditableType(): string
    {
        return $this->auditableType;
    }

    public function getAuditableId(): ?int
    {
        return $this->auditableId;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }
}
