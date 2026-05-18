<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Entities;

class Warehouse
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $code,
        private ?string $address = null,
        private ?string $description = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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
