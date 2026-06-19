<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
use App\Modules\Shared\Application\Services\ReportDataCollector;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
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

        $rows = array_map(
            fn (array $row) => array_values($row),
            $data['rows'] ?? [],
        );

        $export = new class ($data['headers'] ?? [], $rows) implements FromArray, WithHeadings {
            public function __construct(
                private readonly array $headings,
                private readonly array $rows,
            ) {}

            public function headings(): array
            {
                return $this->headings;
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        $content = Excel::raw($export, ExcelWriterType::XLSX);

        return GeneratedReportFile::make($reportType, 'xlsx', self::MIME_TYPE, $content);
    }
}
