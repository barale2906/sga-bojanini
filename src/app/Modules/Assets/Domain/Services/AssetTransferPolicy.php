<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Services;

use App\Modules\Assets\Domain\Entities\Asset;
use App\Modules\Assets\Domain\Enums\AssetTransferType;

/**
 * Reglas para solicitar un traslado de un activo.
 *
 * Es puro (sin persistencia): recibe el estado actual y devuelve el tipo
 * definitivo del traslado, o lanza `DomainException` si no procede.
 */
class AssetTransferPolicy
{
    /**
     * @param int|null $currentSiteId sede del sitio actual (null si está fuera de las instalaciones)
     * @param int|null $targetSiteId  sede del sitio destino (null si el tipo no tiene sitio destino)
     *
     * @throws \DomainException
     */
    public function resolveType(
        Asset $asset,
        AssetTransferType $requested,
        bool $hasPendingTransfer,
        ?int $targetPlaceId = null,
        ?int $currentSiteId = null,
        ?int $targetSiteId = null,
        ?int $targetResponsibleId = null,
    ): AssetTransferType {
        $asset->ensureNotRetired();

        if (! in_array($requested, AssetTransferType::requestable(), true)) {
            throw new \DomainException('Tipo de traslado no permitido.');
        }

        if ($hasPendingTransfer) {
            throw new \DomainException(
                "El activo {$asset->getCode()} tiene un traslado pendiente de firma. Confírmelo o cancélelo primero."
            );
        }

        if ($requested === AssetTransferType::Return) {
            if (! $asset->isOffPremises()) {
                throw new \DomainException("El activo {$asset->getCode()} ya se encuentra en las instalaciones.");
            }

            return $requested;
        }

        if ($requested !== AssetTransferType::Retirement && $asset->isOffPremises()) {
            throw new \DomainException(
                "El activo {$asset->getCode()} está fuera de las instalaciones; registre primero su retorno."
            );
        }

        if ($requested !== AssetTransferType::Relocation) {
            return $requested;
        }

        $samePlace       = $targetPlaceId === $asset->getCurrentPlaceId();
        $sameResponsible = $targetResponsibleId === null || $targetResponsibleId === $asset->getResponsibleId();

        if ($samePlace && $sameResponsible) {
            throw new \DomainException('El traslado no cambia el sitio ni el responsable del activo.');
        }

        return $currentSiteId !== null && $targetSiteId !== null && $currentSiteId !== $targetSiteId
            ? AssetTransferType::SiteTransfer
            : AssetTransferType::Relocation;
    }
}
