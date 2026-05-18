<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class Category
{
    public function __construct(
        private ?int $id,
        private ?int $parentId,
        private string $name,
        private string $code,
        private ?string $description = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    public function hasParent(): bool
    {
        return $this->parentId !== null;
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
