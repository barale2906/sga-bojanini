<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class Supplier
{
    public function __construct(
        private ?int $id,
        private string $name,
        private ?string $taxId = null,
        private ?string $contactName = null,
        private ?string $phone = null,
        private ?string $email = null,
        private ?string $address = null,
        private ?string $notes = null,
        private bool $isActive = true,
        private string $supplierType = 'both',
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
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

    public function getSupplierType(): string
    {
        return $this->supplierType;
    }
}
