<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum FrequencyUnit: string
{
    case Days   = 'days';
    case Months = 'months';

    public function label(): string
    {
        return match ($this) {
            self::Days   => 'Días',
            self::Months => 'Meses',
        };
    }
}
