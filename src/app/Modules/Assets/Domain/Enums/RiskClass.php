<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

/** Clasificación de riesgo de dispositivos médicos (Decreto 4725 de 2005). */
enum RiskClass: string
{
    case I   = 'I';
    case IIa = 'IIa';
    case IIb = 'IIb';
    case III = 'III';

    public function label(): string
    {
        return match ($this) {
            self::I   => 'Clase I — Riesgo bajo',
            self::IIa => 'Clase IIa — Riesgo moderado',
            self::IIb => 'Clase IIb — Riesgo alto',
            self::III => 'Clase III — Riesgo muy alto',
        };
    }
}
