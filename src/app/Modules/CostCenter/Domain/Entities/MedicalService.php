<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

/**
 * Entidad de dominio que representa un servicio médico prestado.
 *
 * Los servicios médicos se registran en los movimientos de salida
 * cuando el centro de costo es externo (pacientes), indicando
 * qué procedimiento o atención motivó el consumo del insumo.
 */
class MedicalService
{
    public function __construct(
        private ?int $id,
        private string $code,
        private string $name,
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
