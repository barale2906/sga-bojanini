<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum BiomedicalClass: string
{
    case Diagnosis      = 'diagnosis';
    case Treatment      = 'treatment';
    case Rehabilitation = 'rehabilitation';
    case Prevention     = 'prevention';
    case Laboratory     = 'laboratory';

    public function label(): string
    {
        return match ($this) {
            self::Diagnosis      => 'Diagnóstico',
            self::Treatment      => 'Tratamiento',
            self::Rehabilitation => 'Rehabilitación',
            self::Prevention     => 'Prevención',
            self::Laboratory     => 'Análisis de laboratorio',
        };
    }
}
