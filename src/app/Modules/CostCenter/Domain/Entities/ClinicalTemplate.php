<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

class ClinicalTemplate
{
    public function __construct(
        private ?int $id,
        private int $medicalServiceId,
        private string $title,
        private string $content,
        private bool $isActive = true,
        private ?string $medicalServiceName = null,
    ) {}

    public static function create(
        int $medicalServiceId,
        string $title,
        string $content,
    ): self {
        return new self(
            id:               null,
            medicalServiceId: $medicalServiceId,
            title:            $title,
            content:          $content,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMedicalServiceId(): int
    {
        return $this->medicalServiceId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getMedicalServiceName(): ?string
    {
        return $this->medicalServiceName;
    }

    public function update(string $title, string $content, bool $isActive): void
    {
        $this->title    = $title;
        $this->content  = $content;
        $this->isActive = $isActive;
    }
}
