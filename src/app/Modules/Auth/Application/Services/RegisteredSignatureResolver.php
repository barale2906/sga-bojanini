<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Auth\Domain\Exceptions\InvalidSignaturePinException;
use App\Modules\Auth\Domain\Exceptions\SignatureNotRegisteredException;
use App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Punto de entrada para que otros módulos (p. ej. Inventario al confirmar un
 * movimiento) obtengan una firma registrada sin acceder a la persistencia de
 * Auth.
 */
class RegisteredSignatureResolver
{
    /** Intentos fallidos de PIN permitidos por usuario que confirma. */
    public const MAX_PIN_ATTEMPTS = 3;

    /** Ventana (segundos) en la que se cuentan los intentos fallidos. */
    public const PIN_DECAY_SECONDS = 900;

    public function __construct(
        private readonly UserSignatureRepositoryInterface $repository,
        private readonly SignaturePinService $pinService,
    ) {}

    /**
     * Firma activa del usuario indicado.
     *
     * @throws SignatureNotRegisteredException si no tiene firma registrada
     */
    public function forUser(int $userId): UserSignature
    {
        return $this->repository->findActiveByUser($userId)
            ?? throw new SignatureNotRegisteredException(
                'No tiene una firma registrada. Regístrela en Mi perfil → Mi firma o firme manualmente.'
            );
    }

    /**
     * Firma activa cuyo PIN coincide.
     *
     * Los intentos fallidos se limitan por `$throttleKey` (normalmente el
     * usuario que está confirmando) para impedir adivinar PIN por fuerza bruta.
     *
     * @param string $field nombre del campo al que se asocia el error de validación
     *
     * @throws InvalidSignaturePinException si el PIN no corresponde a una firma activa
     * @throws ThrottleRequestsException si se superan los intentos permitidos
     */
    public function byPin(string $pin, string $throttleKey, string $field = 'pin'): UserSignature
    {
        $key = 'signature-pin:' . $throttleKey;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PIN_ATTEMPTS)) {
            throw new ThrottleRequestsException(
                'Demasiados intentos de PIN fallidos. Intente de nuevo en ' . RateLimiter::availableIn($key) . ' segundos.'
            );
        }

        $signature = $this->repository->findActiveByPinLookup($this->pinService->lookup($pin));

        if ($signature === null) {
            RateLimiter::hit($key, self::PIN_DECAY_SECONDS);

            throw new InvalidSignaturePinException($field);
        }

        RateLimiter::clear($key);

        return $signature;
    }
}
