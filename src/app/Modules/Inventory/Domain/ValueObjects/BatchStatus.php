<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

enum BatchStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case DEPLETED = 'depleted';

    public function isConsumable(): bool
    {
        return $this === self::ACTIVE;
    }
}
