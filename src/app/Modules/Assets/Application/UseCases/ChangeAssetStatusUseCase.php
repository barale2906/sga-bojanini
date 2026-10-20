<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\Services\AssetMovementRecorder;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ChangeAssetStatusUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetMovementRecorder $movements,
    ) {}

    public function execute(int $id, AssetStatus $status, ?string $reason, int $userId): void
    {
        DB::transaction(function () use ($id, $status, $reason, $userId) {
            $asset = $this->assets->findByIdForUpdate($id)
                ?? throw new \DomainException("Activo con id {$id} no encontrado.");

            $from = $asset->getStatus();
            $asset->changeStatus($status);
            $this->assets->updateStatus($id, $status);

            $this->movements->record(
                assetId:     $id,
                type:        AssetMovementType::StatusChange,
                userId:      $userId,
                description: $reason,
                fromStatus:  $from,
                toStatus:    $status,
            );
        });
    }
}
