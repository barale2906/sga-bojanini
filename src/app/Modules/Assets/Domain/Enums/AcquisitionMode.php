<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AcquisitionMode: string
{
    case Purchase = 'purchase';
    case Donation = 'donation';
    case Comodato = 'comodato';
    case Leasing  = 'leasing';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Compra',
            self::Donation => 'Donación',
            self::Comodato => 'Comodato',
            self::Leasing  => 'Leasing',
        };
    }
}
