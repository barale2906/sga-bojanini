<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Services;

/**
 * Genera códigos internos numéricos secuenciales con relleno de ceros
 * (`000001`–`999999`), aptos para imprimirse como Code128.
 *
 * El llamador entrega el mayor código existente y es responsable de
 * invocarlo dentro de una transacción para evitar colisiones.
 */
class SequentialCodeGenerator
{
    public const DEFAULT_LENGTH = 6;

    public function generate(int $maxExisting, int $length = self::DEFAULT_LENGTH): string
    {
        $next = $maxExisting + 1;

        if (strlen((string) $next) > $length) {
            throw new \DomainException("Se agotó la secuencia de códigos de {$length} dígitos.");
        }

        return str_pad((string) $next, $length, '0', STR_PAD_LEFT);
    }
}
