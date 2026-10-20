<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

/**
 * Tipos de traslado de un activo.
 *
 * La firma (entrega + recibe) es obligatoria solo cuando el activo sale del
 * edificio: traslado a otra sede, reparación externa, préstamo y baja.
 * Los traslados internos y los retornos se aplican de inmediato y la firma
 * es opcional.
 */
enum AssetTransferType: string
{
    /** Cambio de sitio y/o responsable dentro de la misma sede. */
    case Relocation = 'relocation';

    /** Cambio a un sitio de otra sede (sale del edificio). */
    case SiteTransfer = 'site_transfer';

    /** Sale a reparación o mantenimiento con un proveedor externo. */
    case ExternalRepair = 'external_repair';

    /** Préstamo fuera de las instalaciones. */
    case Loan = 'loan';

    /** Regresa a las instalaciones desde reparación externa o préstamo. */
    case Return = 'return';

    /** Baja definitiva del activo. */
    case Retirement = 'retirement';

    public function label(): string
    {
        return match ($this) {
            self::Relocation     => 'Traslado interno',
            self::SiteTransfer   => 'Traslado entre sedes',
            self::ExternalRepair => 'Salida a reparación externa',
            self::Loan           => 'Préstamo externo',
            self::Return         => 'Retorno a las instalaciones',
            self::Retirement     => 'Baja',
        };
    }

    public function requiresSignature(): bool
    {
        return in_array($this, [self::SiteTransfer, self::ExternalRepair, self::Loan, self::Retirement], true);
    }

    /** Tras aplicarse, el activo queda fuera de las instalaciones. */
    public function leavesPremises(): bool
    {
        return in_array($this, [self::ExternalRepair, self::Loan, self::Retirement], true);
    }

    /** Tipos que el cliente puede solicitar (`site_transfer` se deduce de `relocation`). */
    public static function requestable(): array
    {
        return [self::Relocation, self::ExternalRepair, self::Loan, self::Return, self::Retirement];
    }
}
