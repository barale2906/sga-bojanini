<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CostCenter\Domain\Services\MedicalServiceImportService;
use App\Modules\CostCenter\Infrastructure\Import\MedicalServicesImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportMedicalServicesCommand extends Command
{
    protected $signature = 'import:medical-services {path : Ruta del archivo .xlsx, .xls o .csv}';

    protected $description = 'Importa de forma masiva servicios médicos y procedimientos (con su tarifa inicial) desde un archivo Excel/CSV';

    public function handle(MedicalServiceImportService $service): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("El archivo no existe: {$path}");

            return self::FAILURE;
        }

        $import = new MedicalServicesImport;
        Excel::import($import, $path);

        $results = $service->import($import->getRows());

        $this->info("Total filas:  {$results['total']}");
        $this->info("Exitosas:     {$results['success']}");
        $this->info("Fallidas:     {$results['failed']}");

        foreach ($results['errors'] as $error) {
            $this->error("Fila {$error['row']}: ".json_encode($error['errors'], JSON_UNESCAPED_UNICODE));
        }

        return $results['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
