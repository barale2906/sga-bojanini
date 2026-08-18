<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Domain\Enums;

enum PaymentStatus: string
{
    case Unpaid  = 'unpaid';
    case Partial = 'partial';
    case Paid    = 'paid';
}
