<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

use Carbon\Carbon;
use DateTimeInterface;

enum BatchStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case DEPLETED = 'depleted';

    public function isConsumable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Deriva el estado de un lote a partir de su stock total y su fecha de
     * vencimiento:
     *  - Con stock: `expired` si la fecha ya pasó, `active` en caso contrario.
     *  - Sin stock: `depleted`, salvo que el lote ya estuviera marcado como
     *    `expired` (p. ej. tras una baja por vencimiento), que se conserva.
     */
    public static function resolve(float $totalQuantity, DateTimeInterface $expirationDate, ?self $current = null): self
    {
        if ($totalQuantity <= 0) {
            return $current === self::EXPIRED ? self::EXPIRED : self::DEPLETED;
        }

        return Carbon::instance($expirationDate)->startOfDay()->lt(Carbon::today())
            ? self::EXPIRED
            : self::ACTIVE;
    }
}
