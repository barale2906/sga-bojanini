<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\ImportAssetsUseCase;
use App\Modules\Assets\Infrastructure\Export\AssetImportTemplateBuilder;
use App\Modules\Assets\Infrastructure\Import\AssetsImport;
use App\Modules\Catalog\Infrastructure\Http\Requests\ImportFileRequest;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Activos — Importación masiva
 *
 * Carga inicial o masiva de activos con plantilla Excel (incluye hojas de
 * vida opcionales y planes de mantenimiento).
 */
class AssetImportController extends Controller
{
    use ApiResponse;

    /** Descargar la plantilla (se genera con los catálogos vigentes). */
    public function template(AssetImportTemplateBuilder $builder): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'asset_template_');
        (new Xlsx($builder->build()))->save($tempPath);

        return response()->download($tempPath, 'plantilla_activos.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Importar archivo.
     *
     * @response 200 {"success": true, "data": {"total": 10, "created": 9, "failed": 1, "errors": [{"sheet": "Activos", "row": 5, "errors": {"category_code": ["La categoría no existe."]}}], "related_catalogs": {}, "technical_profiles": {}}}
     */
    public function import(ImportFileRequest $request, ImportAssetsUseCase $useCase): JsonResponse
    {
        $reader = new AssetsImport();
        Excel::import($reader, $request->file('file'));

        $results = $useCase->execute(
            $reader->rowsBySheet(),
            $request->file('file')->getClientOriginalName(),
            (int) $request->user()->id,
        );

        return $this->success($results, 'Importación de activos finalizada');
    }
}
