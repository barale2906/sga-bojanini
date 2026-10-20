<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTechnicalProfileModel;

/**
 * Crea o actualiza la hoja de vida técnica de un equipo (biomédico o de
 * cómputo). Los campos que no corresponden al tipo del equipo se descartan.
 */
class UpsertTechnicalProfileUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    /** @param array<string, mixed> $attributes campos de AssetTechnicalProfileModel::FIELDS */
    public function execute(int $assetId, array $attributes): AssetTechnicalProfileModel
    {
        $asset = $this->assets->findById($assetId)
            ?? throw new \DomainException("Activo con id {$assetId} no encontrado.");

        $asset->ensureSupportsMaintenance();

        $excluded = $asset->getAssetType() === AssetType::Biomedical
            ? AssetTechnicalProfileModel::COMPUTING_FIELDS
            : AssetTechnicalProfileModel::BIOMEDICAL_FIELDS;

        $values = array_intersect_key($attributes, array_flip(AssetTechnicalProfileModel::FIELDS));
        $values = array_diff_key($values, array_flip($excluded));

        return AssetTechnicalProfileModel::updateOrCreate(['asset_id' => $assetId], $values);
    }
}
