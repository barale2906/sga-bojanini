<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetData;
use App\Modules\Assets\Application\Services\AssetMovementRecorder;
use App\Modules\Assets\Domain\Entities\Asset;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTechnicalProfileModel;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza los datos generales de un activo. La ubicación, el responsable
 * y el estado no se modifican aquí (traslados / cambio de estado).
 */
class UpdateAssetUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetCategoryRepositoryInterface $categories,
        private readonly AssetMovementRecorder $movements,
    ) {}

    public function execute(int $id, AssetData $data, int $userId): Asset
    {
        $current = $this->assets->findById($id)
            ?? throw new \DomainException("Activo con id {$id} no encontrado.");

        $current->ensureNotRetired();

        $category = $this->categories->findById($data->categoryId)
            ?? throw new \DomainException('La categoría no existe.');

        if ($category->getId() !== $current->getCategoryId()) {
            $this->ensureCategoryChangeAllowed($current, $category->getAssetType()->requiresMaintenance());
        }

        return DB::transaction(function () use ($current, $category, $data, $userId) {
            $updated = $this->assets->save(new Asset(
                id:                  $current->getId(),
                code:                $current->getCode(),
                name:                trim($data->name),
                categoryId:          $category->getId(),
                assetType:           $category->getAssetType(),
                status:              $current->getStatus(),
                currentPlaceId:      $current->getCurrentPlaceId(),
                isOffPremises:       $current->isOffPremises(),
                offPremisesLocation: $current->getOffPremisesLocation(),
                responsibleId:       $current->getResponsibleId(),
                description:         $data->description,
                brand:               $data->brand,
                model:               $data->model,
                serial:              $data->serial,
                acquisitionDate:     $data->acquisitionDate,
                acquisitionValue:    $data->acquisitionValue,
                supplierId:          $data->supplierId,
                invoiceNumber:       $data->invoiceNumber,
                warrantyUntil:       $data->warrantyUntil,
                notes:               $data->notes,
            ));

            $this->movements->record($updated->getId(), AssetMovementType::Update, $userId, 'Actualización de datos generales.');

            return $updated;
        });
    }

    /** Un activo con hoja de vida o planes no puede pasar a una categoría de tipo general. */
    private function ensureCategoryChangeAllowed(Asset $asset, bool $targetRequiresMaintenance): void
    {
        if ($targetRequiresMaintenance) {
            return;
        }

        $hasMaintenanceData = AssetTechnicalProfileModel::where('asset_id', $asset->getId())->exists()
            || AssetMaintenancePlanModel::where('asset_id', $asset->getId())->exists();

        if ($hasMaintenanceData) {
            throw new \DomainException(
                'No se puede cambiar a una categoría de tipo general: el activo tiene hoja de vida o planes de mantenimiento.'
            );
        }
    }
}
