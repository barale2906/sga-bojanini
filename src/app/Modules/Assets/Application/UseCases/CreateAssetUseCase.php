<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetData;
use App\Modules\Assets\Application\Services\AssetCustodyService;
use App\Modules\Assets\Application\Services\AssetMovementRecorder;
use App\Modules\Assets\Domain\Entities\Asset;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetPlaceRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCommentModel;
use App\Modules\Shared\Domain\Services\SequentialCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Registra un activo: genera su código secuencial, abre su historial de
 * ubicaciones y deja constancia en la bitácora. Lo usan el registro manual
 * y la importación masiva.
 */
class CreateAssetUseCase
{
    /** Reintentos si dos registros simultáneos obtienen el mismo código. */
    private const CODE_ATTEMPTS = 3;

    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetCategoryRepositoryInterface $categories,
        private readonly AssetPlaceRepositoryInterface $places,
        private readonly SequentialCodeGenerator $codeGenerator,
        private readonly AssetCustodyService $custody,
        private readonly AssetMovementRecorder $movements,
    ) {}

    public function execute(AssetData $data, int $userId, AssetMovementType $origin = AssetMovementType::Registration): Asset
    {
        $category = $this->categories->findById($data->categoryId);

        if ($category === null || ! $category->isActive()) {
            throw new \DomainException('La categoría no existe o está inactiva.');
        }

        $place = $data->placeId !== null ? $this->places->findById($data->placeId) : null;

        if ($place === null || ! $place->isActive()) {
            throw new \DomainException('El sitio de ubicación no existe o está inactivo.');
        }

        $status = AssetStatus::from($data->status ?? AssetStatus::Operational->value);

        if ($status === AssetStatus::Retired) {
            throw new \DomainException('Un activo no puede registrarse dado de baja.');
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $this->persist($data, $category->getAssetType(), $status, $place->getId(), $userId, $origin));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::CODE_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function persist(
        AssetData $data,
        AssetType $assetType,
        AssetStatus $status,
        int $placeId,
        int $userId,
        AssetMovementType $origin,
    ): Asset {
        $asset = $this->assets->create(new Asset(
            id:               null,
            code:             $this->codeGenerator->generate($this->assets->getMaxCode()),
            name:             trim($data->name),
            categoryId:       $data->categoryId,
            assetType:        $assetType,
            status:           $status,
            currentPlaceId:   $placeId,
            responsibleId:    $data->responsibleId,
            description:      $data->description,
            brand:            $data->brand,
            model:            $data->model,
            serial:           $data->serial,
            acquisitionDate:  $data->acquisitionDate,
            acquisitionValue: $data->acquisitionValue,
            supplierId:       $data->supplierId,
            invoiceNumber:    $data->invoiceNumber,
            warrantyUntil:    $data->warrantyUntil,
            notes:            $data->notes,
        ), $userId);

        $this->custody->initialize($asset->getId(), $placeId, $userId);

        if ($data->initialComment !== null && trim($data->initialComment) !== '') {
            AssetCommentModel::create([
                'asset_id' => $asset->getId(),
                'comment'  => trim($data->initialComment),
                'user_id'  => $userId,
            ]);
        }

        $this->movements->record(
            assetId:  $asset->getId(),
            type:     $origin,
            userId:   $userId,
            toStatus: $status,
        );

        return $asset;
    }
}
