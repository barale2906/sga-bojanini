<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\DeleteAssetDocumentUseCase;
use App\Modules\Assets\Application\UseCases\UploadAssetDocumentUseCase;
use App\Modules\Assets\Infrastructure\Http\Requests\UploadAssetDocumentRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetDocumentResource;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetDocumentModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Activos — Documentos y soportes
 *
 * Documentos del activo (manuales, factura, garantía, INVIMA…) y soportes
 * opcionales de mantenimientos. Se almacenan en disco privado y solo se
 * descargan autenticado.
 */
class AssetDocumentController extends Controller
{
    use ApiResponse;

    /**
     * @queryParam maintenance_id integer Solo soportes de un mantenimiento. Example: 4
     */
    public function index(Request $request, int $assetId): JsonResponse
    {
        AssetModel::withTrashed()->findOrFail($assetId);

        $documents = AssetDocumentModel::with('uploader')
            ->where('asset_id', $assetId)
            ->when($request->filled('maintenance_id'), fn ($q) => $q->where('maintenance_id', $request->integer('maintenance_id')))
            ->orderByDesc('id')
            ->get();

        return $this->success(AssetDocumentResource::collection($documents), 'Documentos del activo');
    }

    /** Cargar documento (multipart/form-data). PDF/JPG/PNG, máx. 10 MB. */
    public function store(UploadAssetDocumentRequest $request, int $assetId, UploadAssetDocumentUseCase $useCase): JsonResponse
    {
        $document = $useCase->execute($assetId, $request->toData(), (int) $request->user()->id);

        return $this->created(new AssetDocumentResource($document->load('uploader')), 'Documento cargado');
    }

    public function download(int $id): StreamedResponse
    {
        $document = AssetDocumentModel::findOrFail($id);
        $disk     = Storage::disk(UploadAssetDocumentUseCase::DISK);

        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->original_name, ['Content-Type' => $document->mime_type]);
    }

    public function destroy(int $id, DeleteAssetDocumentUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Documento eliminado');
    }
}
