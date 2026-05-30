<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Domain\Services\ProductImportService;
use App\Modules\Catalog\Domain\Services\SupplierImportService;
use App\Modules\Catalog\Infrastructure\Http\Requests\ImportFileRequest;
use App\Modules\Catalog\Infrastructure\Import\ProductsImport;
use App\Modules\Catalog\Infrastructure\Import\SuppliersImport;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ImportLogModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    use ApiResponse;

    public function importProducts(ImportFileRequest $request, ProductImportService $service): JsonResponse
    {
        $import = new ProductsImport();
        Excel::import($import, $request->file('file'));

        $results = $service->import($import->getRows());

        ImportLogModel::create([
            'file_name'    => $request->file('file')->getClientOriginalName(),
            'entity_type'  => 'products',
            'total_rows'   => $results['total'],
            'success_rows' => $results['success'],
            'error_rows'   => $results['failed'],
            'errors'       => $results['errors'],
            'user_id'      => Auth::id(),
        ]);

        return $this->success($results, 'Importación de productos finalizada');
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

    public function downloadTemplate(string $entity): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $templates = [
            'products'  => storage_path('app/templates/products_template.xlsx'),
            'suppliers' => storage_path('app/templates/suppliers_template.xlsx'),
        ];

        if (! isset($templates[$entity])) {
            return $this->error('Plantilla no encontrada', 404);
        }

        if (! file_exists($templates[$entity])) {
            return $this->error('El archivo de plantilla no existe. Ejecute: php artisan sga:generate-import-templates', 404);
        }

        return response()->download($templates[$entity]);
    }
}
