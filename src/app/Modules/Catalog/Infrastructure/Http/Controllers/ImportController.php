<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Domain\Services\CategoryImportService;
use App\Modules\Catalog\Domain\Services\ProductClassificationImportService;
use App\Modules\Catalog\Domain\Services\ProductImportService;
use App\Modules\Catalog\Domain\Services\SupplierImportService;
use App\Modules\Catalog\Domain\Services\UnitOfMeasureImportService;
use App\Modules\Catalog\Infrastructure\Export\ImportTemplateBuilder;
use App\Modules\Catalog\Infrastructure\Http\Requests\ImportFileRequest;
use App\Modules\Catalog\Infrastructure\Import\ProductsImport;
use App\Modules\Catalog\Infrastructure\Import\SuppliersImport;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ImportLogModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    use ApiResponse;

    public function importProducts(
        ImportFileRequest $request,
        ProductImportService $service,
        CategoryImportService $categoryService,
        UnitOfMeasureImportService $unitOfMeasureService,
        ProductClassificationImportService $classificationService,
    ): JsonResponse {
        $import = new ProductsImport();
        Excel::import($import, $request->file('file'));

        // Las hojas de catálogo se procesan ANTES que "Productos" para que los
        // códigos nuevos creados aquí estén disponibles al validar
        // category_code / unit_abbreviation / classification_code.
        $categoryResults = $categoryService->import($import->getCategoryRows());
        $unitOfMeasureResults = $unitOfMeasureService->import($import->getUnitOfMeasureRows());
        $classificationResults = $classificationService->import($import->getClassificationRows());

        $results = $service->import($import->getRows());

        $fileName = $request->file('file')->getClientOriginalName();

        ImportLogModel::create([
            'file_name'    => $fileName,
            'entity_type'  => 'products',
            'total_rows'   => $results['total'],
            'success_rows' => $results['success'],
            'error_rows'   => $results['failed'],
            'errors'       => $results['errors'],
            'user_id'      => Auth::id(),
        ]);

        foreach ([
            'categories'       => $categoryResults,
            'units_of_measure' => $unitOfMeasureResults,
            'classifications'  => $classificationResults,
        ] as $entityType => $catalogResults) {
            if ($catalogResults['total'] === 0) {
                continue;
            }

            ImportLogModel::create([
                'file_name'    => $fileName,
                'entity_type'  => $entityType,
                'total_rows'   => $catalogResults['total'],
                'success_rows' => $catalogResults['created'],
                'error_rows'   => $catalogResults['failed'],
                'errors'       => $catalogResults['errors'],
                'user_id'      => Auth::id(),
            ]);
        }

        return $this->success([
            ...$results,
            'related_catalogs' => [
                'categories'       => $categoryResults,
                'units_of_measure' => $unitOfMeasureResults,
                'classifications'  => $classificationResults,
            ],
        ], 'Importación de productos finalizada');
    }

    public function importSuppliers(ImportFileRequest $request, SupplierImportService $service): JsonResponse
    {
        $import = new SuppliersImport();
        Excel::import($import, $request->file('file'));

        $results = $service->import($import->getRows());

        ImportLogModel::create([
            'file_name'    => $request->file('file')->getClientOriginalName(),
            'entity_type'  => 'suppliers',
            'total_rows'   => $results['total'],
            'success_rows' => $results['success'],
            'error_rows'   => $results['failed'],
            'errors'       => $results['errors'],
            'user_id'      => Auth::id(),
        ]);

        return $this->success($results, 'Importación de proveedores finalizada');
    }

    public function downloadTemplate(string $entity, ImportTemplateBuilder $builder): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        if (! in_array($entity, ImportTemplateBuilder::ENTITIES, true)) {
            return $this->error('Plantilla no encontrada', 404);
        }

        $spreadsheet = $builder->build($entity);

        $tempPath = tempnam(sys_get_temp_dir(), 'import_template_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, "{$entity}_template.xlsx")->deleteFileAfterSend(true);
    }
}
