<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class UnitOfMeasure
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $abbreviation,
        private bool $isActive = true,
        private bool $isBase = false,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAbbreviation(): string
    {
        return $this->abbreviation;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isBase(): bool
    {
        return $this->isBase;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
