<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;

/**
 * Entidad de dominio que representa un servicio médico o un procedimiento.
 *
 * Estructura en árbol: un servicio puede tener procedimientos como hijos.
 * Solo los nodos de tipo PROCEDURE pueden tener precios y registros de paciente.
 */
class MedicalService
{
    public function __construct(
        private ?int $id,
        private string $code,
        private string $name,
        private ?string $description,
        private MedicalServiceType $type = MedicalServiceType::SERVICE,
        private ?int $parentId = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getType(): MedicalServiceType
    {
        return $this->type;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isService(): bool
    {
        return $this->type->isService();
    }

    public function isProcedure(): bool
    {
        return $this->type->isProcedure();
    }

    public function isRoot(): bool
    {
        return $this->parentId === null;
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
