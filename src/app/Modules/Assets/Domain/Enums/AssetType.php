<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

/**
 * Tipo de activo, definido por su categoría. Determina si el activo lleva
 * hoja de vida técnica y gestión de mantenimiento.
 */
enum AssetType: string
{
    case Biomedical = 'biomedical';
    case Computing  = 'computing';
    case General    = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Biomedical => 'Equipo biomédico',
            self::Computing  => 'Equipo de cómputo',
            self::General    => 'General (mobiliario y otros)',
        };
    }

    /** Hoja de vida, planes y órdenes de mantenimiento solo aplican a equipos. */
    public function requiresMaintenance(): bool
    {
        return $this !== self::General;
    }
}
