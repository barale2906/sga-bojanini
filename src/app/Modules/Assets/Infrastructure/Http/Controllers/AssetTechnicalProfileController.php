<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\UpsertTechnicalProfileUseCase;
use App\Modules\Assets\Infrastructure\Http\Requests\TechnicalProfileRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetTechnicalProfileResource;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTechnicalProfileModel;
use App\Modules\Assets\Infrastructure\Pdf\AssetPdfRenderer;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @group Activos — Hoja de vida
 *
 * Hoja de vida técnica de equipos biomédicos y de cómputo.
 */
class AssetTechnicalProfileController extends Controller
{
    use ApiResponse;

    public function show(int $assetId): JsonResponse
    {
        AssetModel::findOrFail($assetId);

        $profile = AssetTechnicalProfileModel::where('asset_id', $assetId)->first();

        return $this->success($profile ? new AssetTechnicalProfileResource($profile) : null, 'Hoja de vida');
    }

    /**
     * Crear o actualizar la hoja de vida.
     *
     * @response 409 {"success": false, "message": "El activo 000004 es de tipo general: no lleva hoja de vida ni mantenimientos."}
     */
    public function update(TechnicalProfileRequest $request, int $assetId, UpsertTechnicalProfileUseCase $useCase): JsonResponse
    {
        $profile = $useCase->execute($assetId, $request->validated());

        return $this->success(new AssetTechnicalProfileResource($profile), 'Hoja de vida actualizada');
    }

    /** Hoja de vida completa en PDF (datos, ubicaciones, mantenimientos, documentos y comentarios). */
    public function pdf(int $assetId, AssetPdfRenderer $renderer): Response
    {
        return $renderer->lifeSheet(AssetModel::withTrashed()->findOrFail($assetId));
    }
}
