<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Export;

use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Construye el archivo Excel de plantilla para la importación masiva de
 * servicios médicos y procedimientos (con su tarifa inicial).
 */
class MedicalServiceImportTemplateBuilder
{
    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $this->writeDataSheet(
            $spreadsheet->getActiveSheet(),
            'Servicios y Procedimientos',
            ['type', 'code', 'name', 'parent_code', 'description', 'is_active', 'unit_price', 'effective_from', 'effective_to', 'notes'],
            [
                ['service', 'SERV-CONS', 'Consulta Externa', '', 'Servicio de consulta externa', 'TRUE', '', '', '', ''],
                ['procedure', 'PROC-CONS-GEN', 'Consulta General', 'SERV-CONS', 'Consulta médica general', 'TRUE', '50000', '2026-01-01', '', ''],
            ],
        );

        $this->addInstructionsSheet($spreadsheet, [
            ['Columna', 'Obligatorio', 'Tipo / formato', 'Descripción y valores válidos'],
            ['type', 'Sí', 'Texto', 'Valores válidos: "service" (servicio) o "procedure" (procedimiento).'],
            ['code', 'Sí', 'Texto (máx. 20)', 'Código único. No debe repetirse con registros existentes ni con otras filas del archivo.'],
            ['name', 'Sí', 'Texto (máx. 100)', 'Nombre del servicio o procedimiento.'],
            ['parent_code', 'Depende', 'Texto', 'Para "procedure" es obligatorio: código del servicio al que pertenece (debe existir o estar definido en una fila "service" de este mismo archivo). Para "service" es opcional: úselo solo si pertenece a otro servicio (jerarquía de servicios).'],
            ['description', 'No', 'Texto', 'Descripción.'],
            ['is_active', 'No', 'Booleano', 'Valores aceptados: TRUE/FALSE, 1/0, yes/no. Si se deja vacío se asume TRUE.'],
            ['unit_price', 'Solo procedure', 'Decimal >= 0', 'Valor/tarifa del procedimiento. Obligatorio para "procedure", se ignora para "service".'],
            ['effective_from', 'Solo procedure', 'Fecha AAAA-MM-DD', 'Fecha desde la cual aplica la tarifa. Obligatorio para "procedure".'],
            ['effective_to', 'No', 'Fecha AAAA-MM-DD', 'Fecha hasta la cual aplica la tarifa (opcional). Solo aplica a "procedure".'],
            ['notes', 'No', 'Texto', 'Notas sobre la tarifa. Solo aplica a "procedure".'],
        ], [
            'La primera fila debe conservar exactamente estos nombres de columna (en minúsculas, sin tildes ni espacios).',
            'Las filas completamente vacías se ignoran.',
            'Las filas "service" se procesan ANTES que las "procedure", sin importar el orden en el archivo, para que los procedimientos puedan referenciar mediante parent_code cualquier servicio definido en el mismo archivo.',
            'Si una fila "service" referencia otra fila "service" mediante parent_code (jerarquía de servicios), el servicio padre debe estar definido en una fila anterior del archivo o existir previamente.',
            'Por cada "procedure" creado correctamente se registra también su tarifa inicial (unit_price y effective_from) en la tabla de tarifas de procedimientos.',
            'Esta importación solo crea registros nuevos; no actualiza servicios ni procedimientos existentes.',
            'La hoja "Servicios existentes" muestra los servicios (type=service) vigentes que ya pueden usarse en parent_code.',
        ]);

        $this->addReferenceSheet(
            $spreadsheet,
            'Servicios existentes',
            ['code', 'name'],
            MedicalServiceModel::where('type', MedicalServiceType::SERVICE->value)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn ($s) => [$s->code, $s->name])
                ->all(),
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, string>>  $exampleRows
     */
    private function writeDataSheet(Worksheet $sheet, string $title, array $headers, array $exampleRows): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($exampleRows as $rowIndex => $exampleRow) {
            foreach ($exampleRow as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $rowIndex + 2, $value);
            }
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }

    /**
     * @param  array<int, array<int, string>>  $rows  primera fila es el encabezado
     * @param  string[]  $notes
     */
    private function addInstructionsSheet(Spreadsheet $spreadsheet, array $rows, array $notes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instrucciones');

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($rows[0]), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $notesStartRow = count($rows) + 2;
        $sheet->setCellValueByColumnAndRow(1, $notesStartRow, 'Notas generales');
        $sheet->getStyle("A{$notesStartRow}")->getFont()->setBold(true);

        foreach ($notes as $index => $note) {
            $sheet->setCellValueByColumnAndRow(1, $notesStartRow + 1 + $index, '- '.$note);
        }
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, string|null>>  $rows
     */
    private function addReferenceSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
            }
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }
}
