<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

use DateTimeImmutable;

/**
 * DTO para crear o actualizar la tarifa de un procedimiento médico.
 *
 * @property-read int                  $medicalServiceId ID del procedimiento (type=procedure)
 * @property-read float                $unitPrice        Precio unitario
 * @property-read DateTimeImmutable    $effectiveFrom    Fecha de vigencia inicial
 * @property-read ?DateTimeImmutable   $effectiveTo      Fecha de vencimiento (null = sin vencimiento)
 * @property-read bool                 $isActive         Estado activo/inactivo
 * @property-read ?string              $notes            Notas opcionales
 */
readonly class ProcedurePriceData
{
    public function __construct(
        public int $medicalServiceId,
        public float $unitPrice,
        public DateTimeImmutable $effectiveFrom,
        public ?DateTimeImmutable $effectiveTo = null,
        public bool $isActive = true,
        public ?string $notes = null,
    ) {}
}
