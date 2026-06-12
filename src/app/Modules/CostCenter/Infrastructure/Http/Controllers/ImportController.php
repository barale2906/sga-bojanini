<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Http\Requests\ImportFileRequest;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ImportLogModel;
use App\Modules\CostCenter\Domain\Services\MedicalServiceImportService;
use App\Modules\CostCenter\Infrastructure\Export\MedicalServiceImportTemplateBuilder;
use App\Modules\CostCenter\Infrastructure\Import\MedicalServicesImport;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    use ApiResponse;

    public function importMedicalServices(ImportFileRequest $request, MedicalServiceImportService $service): JsonResponse
    {
        $import = new MedicalServicesImport;
        Excel::import($import, $request->file('file'));

        $results = $service->import($import->getRows());

        ImportLogModel::create([
            'file_name' => $request->file('file')->getClientOriginalName(),
            'entity_type' => 'medical_services',
            'total_rows' => $results['total'],
            'success_rows' => $results['success'],
            'error_rows' => $results['failed'],
            'errors' => $results['errors'],
            'user_id' => Auth::id(),
        ]);

        return $this->success($results, 'Importación de servicios y procedimientos finalizada');
    }

    public function downloadTemplate(MedicalServiceImportTemplateBuilder $builder): BinaryFileResponse
    {
        $spreadsheet = $builder->build();

        $tempPath = tempnam(sys_get_temp_dir(), 'import_template_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, 'medical_services_template.xlsx')->deleteFileAfterSend(true);
    }
}
