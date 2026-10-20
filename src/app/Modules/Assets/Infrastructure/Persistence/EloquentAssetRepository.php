<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence;

use App\Modules\Assets\Domain\Entities\Asset;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use Illuminate\Support\Facades\DB;

class EloquentAssetRepository implements AssetRepositoryInterface
{
    public function findById(int $id): ?Asset
    {
        $model = AssetModel::with('category')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdForUpdate(int $id): ?Asset
    {
        $model = AssetModel::with('category')->lockForUpdate()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function create(Asset $asset, int $createdBy): Asset
    {
        $model = new AssetModel();
        $model->fill([
            ...$this->detailAttributes($asset),
            'code'                  => $asset->getCode(),
            'status'                => $asset->getStatus(),
            'current_place_id'      => $asset->getCurrentPlaceId(),
            'is_off_premises'       => $asset->isOffPremises(),
            'off_premises_location' => $asset->getOffPremisesLocation(),
            'responsible_id'        => $asset->getResponsibleId(),
            'created_by'            => $createdBy,
        ])->save();

        return $this->toDomain($model->load('category'));
    }

    public function save(Asset $asset): Asset
    {
        $model = AssetModel::findOrFail($asset->getId());
        $model->fill([
            ...$this->detailAttributes($asset),
            'status' => $asset->getStatus(),
        ])->save();

        return $this->toDomain($model->load('category'));
    }

    public function updateStatus(int $id, AssetStatus $status): void
    {
        AssetModel::findOrFail($id)->update(['status' => $status]);
    }

    public function getMaxCode(): int
    {
        return (int) DB::table('assets')->max('code');
    }

    public function delete(int $id): void
    {
        AssetModel::findOrFail($id)->delete();
    }

    public function hasHistory(int $id): bool
    {
        return AssetMaintenanceModel::where('asset_id', $id)->where('status', MaintenanceStatus::Completed->value)->exists()
            || AssetTransferModel::where('asset_id', $id)->where('status', AssetTransferStatus::Confirmed->value)->exists();
    }

    /** Atributos generales editables (sin ubicación, responsable ni estado). */
    private function detailAttributes(Asset $asset): array
    {
        return [
            'name'              => $asset->getName(),
            'description'       => $asset->getDescription(),
            'category_id'       => $asset->getCategoryId(),
            'brand'             => $asset->getBrand(),
            'model'             => $asset->getModel(),
            'serial'            => $asset->getSerial(),
            'acquisition_date'  => $asset->getAcquisitionDate(),
            'acquisition_value' => $asset->getAcquisitionValue(),
            'supplier_id'       => $asset->getSupplierId(),
            'invoice_number'    => $asset->getInvoiceNumber(),
            'warranty_until'    => $asset->getWarrantyUntil(),
            'notes'             => $asset->getNotes(),
        ];
    }

    private function toDomain(AssetModel $model): Asset
    {
        return new Asset(
            id:                  $model->id,
            code:                $model->code,
            name:                $model->name,
            categoryId:          $model->category_id,
            assetType:           $model->category->asset_type,
            status:              $model->status,
            currentPlaceId:      $model->current_place_id,
            isOffPremises:       (bool) $model->is_off_premises,
            offPremisesLocation: $model->off_premises_location,
            responsibleId:       $model->responsible_id,
            description:         $model->description,
            brand:               $model->brand,
            model:               $model->model,
            serial:              $model->serial,
            acquisitionDate:     $model->acquisition_date?->toDateString(),
            acquisitionValue:    $model->acquisition_value,
            supplierId:          $model->supplier_id,
            invoiceNumber:       $model->invoice_number,
            warrantyUntil:       $model->warranty_until?->toDateString(),
            notes:               $model->notes,
            retiredAt:           $model->retired_at?->toDateString(),
            retirementReason:    $model->retirement_reason,
        );
    }
}
