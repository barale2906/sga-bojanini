<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum PerformedByType: string
{
    /** Técnico interno (usuario del sistema). */
    case Internal = 'internal';

    /** Proveedor externo + nombre del técnico que lo ejecutó. */
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno',
            self::External => 'Externo',
        };
    }
}
