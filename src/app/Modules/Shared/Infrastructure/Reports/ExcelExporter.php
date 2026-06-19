<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
use App\Modules\Shared\Application\Services\ReportDataCollector;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;

class ExcelExporter
{
    private const MIME_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private readonly ReportDataCollector $dataCollector,
    ) {}

    public function export(string $reportType, array $filters): GeneratedReportFile
    {
        $data = $this->dataCollector->collect($reportType, $filters);

        $sheetRows = [];

        if (!empty($data['date_from']) && !empty($data['date_to'])) {
            $sheetRows[] = ["Rango de fechas evaluado: {$data['date_from']} a {$data['date_to']}"];
            $sheetRows[] = [];
        }

        $sheetRows[] = $data['headers'] ?? [];

        foreach ($data['rows'] ?? [] as $row) {
            $sheetRows[] = array_values($row);
        }

        $export = new class ($sheetRows) implements FromArray {
            public function __construct(
                private readonly array $rows,
            ) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        $content = Excel::raw($export, ExcelWriterType::XLSX);

        return GeneratedReportFile::make($reportType, 'xlsx', self::MIME_TYPE, $content);
    }
}
