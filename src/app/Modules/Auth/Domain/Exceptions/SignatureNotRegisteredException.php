<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

/**
 * Se lanza cuando se pide la firma registrada de un usuario que aún no la ha
 * registrado en "Mi firma". Se responde con el 409 genérico de DomainException.
 */
class SignatureNotRegisteredException extends \DomainException
{
}
