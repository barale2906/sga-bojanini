<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetCategoryData;
use App\Modules\Assets\Domain\Entities\AssetCategory;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTechnicalProfileModel;

/**
 * Crea (id null) o actualiza una categoría de activos.
 */
class SaveAssetCategoryUseCase
{
    public function __construct(
        private readonly AssetCategoryRepositoryInterface $repository,
    ) {}

    public function execute(?int $id, AssetCategoryData $data): AssetCategory
    {
        $code = strtoupper(trim($data->code));
        $type = AssetType::from($data->assetType);

        $conflict = $this->repository->findByCode($code);

        if ($conflict !== null && $conflict->getId() !== $id) {
            throw new \DomainException("Ya existe una categoría de activos con el código '{$code}'.");
        }

        if ($id !== null) {
            $existing = $this->repository->findById($id)
                ?? throw new \DomainException("Categoría de activos con id {$id} no encontrada.");

            $this->ensureTypeChangeAllowed($existing, $type);
        }

        return $this->repository->save(new AssetCategory(
            id:          $id,
            code:        $code,
            name:        trim($data->name),
            assetType:   $type,
            description: $data->description,
            isActive:    $data->isActive,
        ));
    }

    /**
     * Pasar a tipo "general" dejaría hojas de vida y planes huérfanos.
     */
    private function ensureTypeChangeAllowed(AssetCategory $existing, AssetType $newType): void
    {
        if ($newType->requiresMaintenance() || ! $existing->getAssetType()->requiresMaintenance()) {
            return;
        }

        $usesMaintenance = AssetTechnicalProfileModel::whereHas('asset', fn ($q) => $q->where('category_id', $existing->getId()))->exists()
            || AssetMaintenancePlanModel::whereHas('asset', fn ($q) => $q->where('category_id', $existing->getId()))->exists();

        if ($usesMaintenance) {
            throw new \DomainException(
                'No se puede cambiar la categoría a tipo general: sus activos ya tienen hoja de vida o planes de mantenimiento.'
            );
        }
    }
}
