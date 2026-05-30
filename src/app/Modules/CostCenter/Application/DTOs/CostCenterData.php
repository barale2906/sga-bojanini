<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

/**
 * DTO para crear o actualizar un centro de costo.
 *
 * @property-read string  $code        Código único (máx. 20 caracteres)
 * @property-read string  $name        Nombre descriptivo
 * @property-read string  $type        Tipo: 'internal' | 'external'
 * @property-read ?string $description Descripción opcional
 * @property-read bool    $isActive    Estado activo/inactivo
 */
readonly class CostCenterData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $type,
        public ?string $description,
        public bool $isActive = true,
    ) {}
}
