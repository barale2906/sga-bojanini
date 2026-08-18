<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Domain\Enums;

enum PaymentMethod: string
{
    case Cash     = 'cash';
    case Transfer = 'transfer';
    case Check    = 'check';
    case Other    = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash     => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::Check    => 'Cheque',
            self::Other    => 'Otro',
        };
    }
}
