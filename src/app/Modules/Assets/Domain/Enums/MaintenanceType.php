<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum MaintenanceType: string
{
    case Preventive               = 'preventive';
    case Calibration              = 'calibration';
    case MetrologicalVerification = 'metrological_verification';
    case Inspection               = 'inspection';
    case Corrective               = 'corrective';

    public function label(): string
    {
        return match ($this) {
            self::Preventive               => 'Preventivo',
            self::Calibration              => 'Calibración',
            self::MetrologicalVerification => 'Verificación metrológica',
            self::Inspection               => 'Inspección',
            self::Corrective               => 'Correctivo',
        };
    }

    /**
     * Tipos que pueden programarse con un plan periódico (el correctivo
     * surge de una falla y se registra como orden puntual).
     *
     * @return self[]
     */
    public static function plannable(): array
    {
        return [self::Preventive, self::Calibration, self::MetrologicalVerification, self::Inspection];
    }
}
