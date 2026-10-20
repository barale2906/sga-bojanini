<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetDocumentModel;

/**
 * Retira un documento (soft delete). El archivo se conserva en disco para
 * trazabilidad; el registro queda en auditoría.
 */
class DeleteAssetDocumentUseCase
{
    public function execute(int $documentId): void
    {
        AssetDocumentModel::findOrFail($documentId)->delete();
    }
}
