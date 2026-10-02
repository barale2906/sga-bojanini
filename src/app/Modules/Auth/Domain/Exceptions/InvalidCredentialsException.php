<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

/**
 * Se lanza cuando el inicio de sesión falla porque el correo no existe
 * o la contraseña no coincide.
 *
 * Se responde con 401 (no autenticado) y no con el 409 genérico de
 * DomainException: un fallo de autenticación no es un conflicto de estado.
 * El mensaje es deliberadamente igual en ambos casos, para no revelar
 * si un correo está registrado o no.
 */
class InvalidCredentialsException extends \DomainException
{
}
