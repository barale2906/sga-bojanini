<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

readonly class ServiceOrderLineData
{
    public function __construct(
        public int $medicalServiceId,
        public float $unitPrice,
        public float $quantity,
        public ?string $discountType = null,
        public ?float $discountValue = null,
        public ?string $notes = null,
        /** @var array<int, array{warehouse_id: int, generic_product_id: int, quantity: float}> */
        public array $supplies = [],
        public ?int $movementDocumentId = null,
    ) {}
}
