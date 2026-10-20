<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetDocumentData;
use App\Modules\Assets\Domain\Enums\AssetDocumentType;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetDocumentModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;

/**
 * Adjunta un documento al activo (manual, factura, garantía…) o un
 * soporte a uno de sus mantenimientos. Se guarda en el disco privado.
 */
class UploadAssetDocumentUseCase
{
    public const DISK = 'local';

    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function execute(int $assetId, AssetDocumentData $data, int $userId): AssetDocumentModel
    {
        if ($this->assets->findById($assetId) === null) {
            throw new \DomainException("Activo con id {$assetId} no encontrado.");
        }

        if ($data->maintenanceId !== null
            && ! AssetMaintenanceModel::where('asset_id', $assetId)->whereKey($data->maintenanceId)->exists()) {
            throw new \DomainException('El mantenimiento no pertenece al activo.');
        }

        $path = $data->file->store("assets/{$assetId}", self::DISK);

        if ($path === false) {
            throw new \RuntimeException('No fue posible guardar el archivo.');
        }

        return AssetDocumentModel::create([
            'asset_id'       => $assetId,
            'maintenance_id' => $data->maintenanceId,
            'document_type'  => AssetDocumentType::from($data->documentType),
            'title'          => $data->title,
            'file_path'      => $path,
            'original_name'  => $data->file->getClientOriginalName(),
            'mime_type'      => (string) $data->file->getMimeType(),
            'size'           => (int) $data->file->getSize(),
            'issued_at'      => $data->issuedAt,
            'expires_at'     => $data->expiresAt,
            'uploaded_by'    => $userId,
        ]);
    }
}
