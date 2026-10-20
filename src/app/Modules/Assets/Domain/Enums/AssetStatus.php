<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetStatus: string
{
    case Operational  = 'operational';
    case Maintenance  = 'maintenance';
    case OutOfService = 'out_of_service';
    case Available    = 'available';
    case Retired      = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Operational  => 'Operativo',
            self::Maintenance  => 'En mantenimiento',
            self::OutOfService => 'Fuera de servicio',
            self::Available    => 'Disponible',
            self::Retired      => 'De baja',
        };
    }

    /**
     * Estados que se pueden fijar manualmente (la baja solo ocurre por un
     * traslado de tipo `retirement`, con firma).
     *
     * @return self[]
     */
    public static function manuallySettable(): array
    {
        return [self::Operational, self::Maintenance, self::OutOfService, self::Available];
    }
}
