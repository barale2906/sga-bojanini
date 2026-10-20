<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCommentModel;

/**
 * Agrega un comentario al activo. Los comentarios se acumulan (no se
 * editan ni eliminan) y pertenecen al equipo sin importar dónde esté.
 */
class AddAssetCommentUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function execute(int $assetId, string $comment, int $userId): AssetCommentModel
    {
        if ($this->assets->findById($assetId) === null) {
            throw new \DomainException("Activo con id {$assetId} no encontrado.");
        }

        return AssetCommentModel::create([
            'asset_id' => $assetId,
            'comment'  => trim($comment),
            'user_id'  => $userId,
        ])->load('user');
    }
}
