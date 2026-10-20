<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum MaintenanceStatus: string
{
    case Scheduled  = 'scheduled';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled  => 'Programado',
            self::InProgress => 'En ejecución',
            self::Completed  => 'Realizado',
            self::Cancelled  => 'Cancelado',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Scheduled || $this === self::InProgress;
    }
}
