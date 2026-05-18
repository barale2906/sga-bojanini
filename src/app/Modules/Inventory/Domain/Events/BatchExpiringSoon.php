<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchExpiringSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $batchId,
        public readonly int $productId,
        public readonly string $lotNumber,
        public readonly string $expirationDate,
        public readonly int $daysUntilExpiry,
    ) {}
}
