<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;

/**
 * DTO para crear o actualizar un servicio médico o procedimiento.
 *
 * @property-read string             $code        Código único (máx. 20 caracteres)
 * @property-read string             $name        Nombre del servicio/procedimiento
 * @property-read ?string            $description Descripción opcional
 * @property-read MedicalServiceType $type        Tipo: service | procedure
 * @property-read ?int               $parentId    ID del servicio padre (requerido si type=procedure)
 * @property-read bool               $isActive    Estado activo/inactivo
 */
readonly class MedicalServiceData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public MedicalServiceType $type = MedicalServiceType::SERVICE,
        public ?int $parentId = null,
        public bool $isActive = true,
    ) {}
}
