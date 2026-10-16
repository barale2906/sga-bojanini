<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

/**
 * Se lanza cuando un PIN de firma no corresponde a ninguna firma activa.
 *
 * Se responde con 422 (error de validación sobre el campo indicado en
 * `$field`) y no con el 409 genérico: es un dato mal digitado, no un
 * conflicto de estado. El mensaje no revela si el PIN existe para un usuario
 * inactivo o eliminado.
 */
class InvalidSignaturePinException extends \DomainException
{
    public function __construct(
        public readonly string $field = 'pin',
        string $message = 'El PIN de firma no es válido.',
    ) {
        parent::__construct($message);
    }
}
