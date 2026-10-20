<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum PowerSource: string
{
    case Electric   = 'electric';
    case Battery    = 'battery';
    case Pneumatic  = 'pneumatic';
    case Mechanical = 'mechanical';
    case Other      = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electric   => 'Eléctrica',
            self::Battery    => 'Batería',
            self::Pneumatic  => 'Neumática',
            self::Mechanical => 'Mecánica',
            self::Other      => 'Otra',
        };
    }
}
