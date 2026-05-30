<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Enums;

enum CostCenterType: string
{
    /** Consumo por áreas internas de la empresa. */
    case Internal = 'internal';

    /** Consumo en atención a pacientes. */
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno',
            self::External => 'Externo (Pacientes)',
        };
    }
}
