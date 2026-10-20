<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Repositories;

use App\Modules\Assets\Domain\Entities\Asset;
use App\Modules\Assets\Domain\Enums\AssetStatus;

/**
 * Persistencia del agregado Activo.
 *
 * `save()` escribe los datos generales y el estado. La ubicación y el
 * responsable los escribe exclusivamente `AssetLocationService` para
 * mantener el historial de ubicaciones consistente.
 */
interface AssetRepositoryInterface
{
    public function findById(int $id): ?Asset;

    /** Bloquea la fila para escritura (usar dentro de una transacción). */
    public function findByIdForUpdate(int $id): ?Asset;

    /** Crea el activo con su código y lo devuelve hidratado. */
    public function create(Asset $asset, int $createdBy): Asset;

    public function save(Asset $asset): Asset;

    public function updateStatus(int $id, AssetStatus $status): void;

    public function getMaxCode(): int;

    public function delete(int $id): void;

    /** Si el activo tiene historia que impide eliminarlo (mantenimientos realizados o traslados confirmados). */
    public function hasHistory(int $id): bool;
}
