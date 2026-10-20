<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

/** Eventos registrados en la bitácora (solo lectura) de un activo. */
enum AssetMovementType: string
{
    case Registration = 'registration';
    case Import       = 'import';
    case Update       = 'update';
    case Transfer     = 'transfer';
    case StatusChange = 'status_change';
    case Maintenance  = 'maintenance';
    case Retirement   = 'retirement';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registro',
            self::Import       => 'Importación',
            self::Update       => 'Actualización',
            self::Transfer     => 'Traslado',
            self::StatusChange => 'Cambio de estado',
            self::Maintenance  => 'Mantenimiento',
            self::Retirement   => 'Baja',
        };
    }
}
