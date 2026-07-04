<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

enum MovementStatus: string
{
    case PENDING_SIGNATURE = 'pending_signature';
    case CONFIRMED         = 'confirmed';
}
