<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMovementCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $movementId,
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly string $movementType,
        public readonly int $quantity,
        public readonly ?int $warehouseToId = null,
    ) {}
}
