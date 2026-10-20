<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetLocationHistoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;

/**
 * Único escritor de la custodia de un activo: sitio vigente, estado
 * "fuera de las instalaciones", responsable e historial de ubicaciones.
 *
 * Siempre debe invocarse dentro de la transacción del caso de uso.
 */
class AssetCustodyService
{
    /** Abre el primer tramo del historial al registrar el activo. */
    public function initialize(int $assetId, int $placeId, int $userId): void
    {
        AssetLocationHistoryModel::create([
            'asset_id'    => $assetId,
            'place_id'    => $placeId,
            'from_date'   => now(),
            'assigned_by' => $userId,
        ]);
    }

    /** Ubica el activo en un sitio de las instalaciones (traslado interno, entre sedes o retorno). */
    public function moveToPlace(AssetModel $asset, int $placeId, int $userId, ?int $transferId = null): void
    {
        $this->openSegment($asset, $userId, $transferId, placeId: $placeId);

        $asset->forceFill([
            'current_place_id'      => $placeId,
            'is_off_premises'       => false,
            'off_premises_location' => null,
        ])->save();
    }

    /** Registra la salida del activo de las instalaciones (reparación externa, préstamo o baja). */
    public function moveOffPremises(AssetModel $asset, string $location, int $userId, ?int $transferId = null): void
    {
        $this->openSegment($asset, $userId, $transferId, externalLocation: $location);

        $asset->forceFill([
            'current_place_id'      => null,
            'is_off_premises'       => true,
            'off_premises_location' => $location,
        ])->save();
    }

    public function assignResponsible(AssetModel $asset, ?int $responsibleId): void
    {
        $asset->forceFill(['responsible_id' => $responsibleId])->save();
    }

    /** Cierra el tramo vigente y abre uno nuevo. */
    private function openSegment(
        AssetModel $asset,
        int $userId,
        ?int $transferId,
        ?int $placeId = null,
        ?string $externalLocation = null,
    ): void {
        $now = now();

        AssetLocationHistoryModel::where('asset_id', $asset->id)
            ->whereNull('to_date')
            ->update(['to_date' => $now]);

        AssetLocationHistoryModel::create([
            'asset_id'          => $asset->id,
            'place_id'          => $placeId,
            'external_location' => $externalLocation,
            'from_date'         => $now,
            'transfer_id'       => $transferId,
            'assigned_by'       => $userId,
        ]);
    }
}
