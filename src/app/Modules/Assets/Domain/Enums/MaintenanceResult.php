<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum MaintenanceResult: string
{
    case Operational                 = 'operational';
    case OperationalWithRestrictions = 'operational_with_restrictions';
    case OutOfService                = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Operational                 => 'Operativo',
            self::OperationalWithRestrictions => 'Operativo con restricciones',
            self::OutOfService                => 'Fuera de servicio',
        };
    }

    /** Estado que toma el activo al registrar el mantenimiento con este resultado. */
    public function resultingAssetStatus(): AssetStatus
    {
        return $this === self::OutOfService ? AssetStatus::OutOfService : AssetStatus::Operational;
    }
}
