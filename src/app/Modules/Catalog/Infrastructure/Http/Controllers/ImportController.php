<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Domain\Services\ProductImportService;
use App\Modules\Catalog\Domain\Services\SupplierImportService;
use App\Modules\Catalog\Infrastructure\Import\ProductsImport;
use App\Modules\Catalog\Infrastructure\Import\SuppliersImport;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ImportLogModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    use ApiResponse;

    public function importProducts(Request $request, ProductImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:10240'],
        ]);

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

    public function importSuppliers(Request $request, SupplierImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:10240'],
        ]);

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
}
