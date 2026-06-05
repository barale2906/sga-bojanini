<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Enums;

enum MedicalServiceType: string
{
    case SERVICE   = 'service';
    case PROCEDURE = 'procedure';

    public function label(): string
    {
        return match($this) {
            self::SERVICE   => 'Servicio',
            self::PROCEDURE => 'Procedimiento',
        };
    }

    public function isService(): bool
    {
        return $this === self::SERVICE;
    }

    public function isProcedure(): bool
    {
        return $this === self::PROCEDURE;
    }
}
