<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

/**
 * Tipos de movimiento de inventario.
 *
 * @see REF-10 en backend.md
 */
enum MovementType: string
{
    case ENTRY = 'entry';
    case EXIT = 'exit';
    case TRANSFER = 'transfer';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case EXPIRATION_WRITE_OFF = 'expiration_write_off';
    case LOSS = 'loss';

    public function reducesStock(): bool
    {
        return match ($this) {
            self::EXIT,
            self::RETURN,
            self::EXPIRATION_WRITE_OFF,
            self::LOSS => true,
            default => false,
        };
    }
}
