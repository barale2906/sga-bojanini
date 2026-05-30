<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

use App\Modules\CostCenter\Domain\Enums\CostCenterType;

/**
 * Entidad de dominio que representa un centro de costo.
 *
 * Un centro de costo agrupa los movimientos de salida de inventario
 * bajo un destino específico. Puede ser interno (área de la empresa)
 * o externo (atención a pacientes).
 */
class CostCenter
{
    public function __construct(
        private ?int $id,
        private string $code,
        private string $name,
        private CostCenterType $type,
        private ?string $description,
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

    public function getType(): CostCenterType
    {
        return $this->type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** Indica si los movimientos bajo este centro requieren datos de paciente y servicio médico. */
    public function isExternal(): bool
    {
        return $this->type === CostCenterType::External;
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
