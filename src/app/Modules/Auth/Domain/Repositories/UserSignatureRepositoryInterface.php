<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repositories;

use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;

/**
 * Persistencia de las firmas registradas por usuario ("Mi firma").
 *
 * Las firmas se versionan: registrar una firma nueva desactiva la anterior en
 * lugar de modificarla. Ningún método lista firmas de varios usuarios: el
 * acceso es siempre por el usuario dueño o por el HMAC de su PIN.
 */
interface UserSignatureRepositoryInterface
{
    /** Firma activa del usuario, o null si no ha registrado ninguna. */
    public function findActiveByUser(int $userId): ?UserSignature;

    /**
     * Firma activa cuyo PIN corresponde al HMAC dado. Solo devuelve firmas de
     * usuarios activos y no eliminados.
     */
    public function findActiveByPinLookup(string $pinLookup): ?UserSignature;

    /**
     * Indica si el HMAC de PIN no se puede asignar: lo usa alguna firma (de
     * cualquier usuario) o está en cuarentena tras haber sido liberado.
     */
    public function pinLookupExists(string $pinLookup): bool;

    /**
     * Crea una nueva versión activa de la firma del usuario y desactiva la
     * anterior. El HMAC del PIN se traslada a la nueva versión.
     */
    public function registerVersion(
        int $userId,
        IdentityDocumentType $documentType,
        string $documentNumber,
        string $signatureData,
        ?string $pinLookup,
    ): UserSignature;

    /** HMAC del PIN de la firma activa del usuario (null si no tiene). */
    public function findActivePinLookup(int $userId): ?string;

    /**
     * Reemplaza el HMAC del PIN de la firma activa del usuario. El PIN anterior
     * queda en cuarentena (no asignable) hasta `$oldPinAvailableAt`.
     */
    public function replacePinLookup(int $userId, string $pinLookup, \DateTimeInterface $oldPinAvailableAt): UserSignature;
}
