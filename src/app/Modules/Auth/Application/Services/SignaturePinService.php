<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface;

/**
 * Genera y calcula el HMAC de los PIN de firma.
 *
 * El PIN, por sí solo, identifica a quien recibe un movimiento (no se pide
 * cédula), así que debe ser único en todo el sistema. Por eso lo genera el
 * sistema y no lo elige el usuario: si el usuario pudiera elegirlo, un
 * "PIN no disponible" le revelaría que otra persona usa ese PIN.
 *
 * Un PIN liberado al regenerarlo queda en cuarentena QUARANTINE_DAYS días
 * antes de poder asignarse de nuevo.
 *
 * Se persiste un HMAC-SHA256 con la APP_KEY (búsqueda determinística por
 * índice único), nunca el PIN en claro. Rotar la APP_KEY invalida todos los
 * PIN: los usuarios tendrían que regenerarlo desde "Mi firma".
 */
class SignaturePinService
{
    public const PIN_LENGTH = 3;

    /**
     * Días que un PIN liberado (al regenerarlo) no puede asignarse a otro
     * usuario: quien conocía el PIN viejo no debe poder firmar como otra persona.
     */
    public const QUARANTINE_DAYS = 180;

    private const MAX_RANDOM_ATTEMPTS = 50;

    public function __construct(
        private readonly UserSignatureRepositoryInterface $repository,
    ) {}

    /**
     * Genera un PIN aleatorio que no esté en uso, ni en cuarentena, ni sea trivial.
     *
     * Con PIN cortos el espacio es pequeño (~974 PIN válidos de 3 dígitos):
     * primero se intenta al azar y, si no se encuentra uno libre, se recorren
     * en orden aleatorio todos los candidatos.
     *
     * @return array{pin: string, lookup: string}
     *
     * @throws \DomainException si ya no quedan PIN disponibles
     */
    public function generateUnique(): array
    {
        for ($attempt = 0; $attempt < self::MAX_RANDOM_ATTEMPTS; $attempt++) {
            $candidate = $this->tryCandidate($this->format(random_int(0, 10 ** self::PIN_LENGTH - 1)));

            if ($candidate !== null) {
                return $candidate;
            }
        }

        $all = range(0, 10 ** self::PIN_LENGTH - 1);
        shuffle($all);

        foreach ($all as $number) {
            $candidate = $this->tryCandidate($this->format($number));

            if ($candidate !== null) {
                return $candidate;
            }
        }

        throw new \DomainException('No hay PIN de firma disponibles. Contacte al administrador.');
    }

    /** HMAC-SHA256 del PIN, usado como clave de búsqueda. */
    public function lookup(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }

    /** @return array{pin: string, lookup: string}|null */
    private function tryCandidate(string $pin): ?array
    {
        if ($this->isTrivial($pin)) {
            return null;
        }

        $lookup = $this->lookup($pin);

        return $this->repository->pinLookupExists($lookup) ? null : ['pin' => $pin, 'lookup' => $lookup];
    }

    private function format(int $number): string
    {
        return str_pad((string) $number, self::PIN_LENGTH, '0', STR_PAD_LEFT);
    }

    /** Descarta PIN fáciles de adivinar: un solo dígito repetido o secuencias. */
    private function isTrivial(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        return str_contains('0123456789', $pin) || str_contains('9876543210', $pin);
    }
}
