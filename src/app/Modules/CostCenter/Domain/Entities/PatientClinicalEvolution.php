<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

use DateTimeImmutable;

class PatientClinicalEvolution
{
    public function __construct(
        private ?int $id,
        private int $patientProcedureRecordId,
        private string $content,
        private int $userId,
        private DateTimeImmutable $recordedAt,
        private ?string $userName = null,
    ) {}

    public static function create(
        int $patientProcedureRecordId,
        string $content,
        int $userId,
        DateTimeImmutable $recordedAt,
    ): self {
        return new self(
            id:                        null,
            patientProcedureRecordId:  $patientProcedureRecordId,
            content:                   $content,
            userId:                    $userId,
            recordedAt:                $recordedAt,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPatientProcedureRecordId(): int
    {
        return $this->patientProcedureRecordId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function updateContent(string $content): void
    {
        $this->content = $content;
    }
}
