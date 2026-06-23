<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Helpers reutilizables para construir plantillas Excel de importación
 * masiva: hoja de datos con encabezado técnico + fila de ayuda en español
 * + ejemplo, hoja de instrucciones y hojas de referencia de catálogos.
 *
 * Usado por los `*TemplateBuilder` de cualquier módulo que necesite
 * generar plantillas de carga (ver `Inventory\Infrastructure\Export\InitialEntryTemplateBuilder`).
 */
class ExcelTemplateWriter
{
    /**
     * Escribe la hoja de datos con: fila 1 = encabezado técnico (el que lee
     * el importador, no se debe traducir), fila 2 = nombre de cada columna
     * en español (solo de referencia visual, se debe borrar antes de cargar
     * el archivo) y fila 3 = ejemplo de datos.
     *
     * @param  string[]  $headers
     * @param  string[]  $spanishLabels
     * @param  string[]  $exampleRow
     */
    public function writeDataSheet(Worksheet $sheet, string $title, array $headers, array $spanishLabels, array $exampleRow): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $spanishLabels[$col] ?? '');
            $sheet->setCellValueByColumnAndRow($col + 1, 3, $exampleRow[$col] ?? '');
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        $sheet->getStyle("A2:{$lastColumn}2")->getFont()->setItalic(true)->setColor(new Color('FF7F7F7F'));
        $sheet->getStyle("A2:{$lastColumn}2")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F2F2');
        $sheet->getStyle("A2:{$lastColumn}2")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(30);

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $sheet->getComment('A1')->getText()->createTextRun(
            'La fila 2 (gris, en español) es solo de referencia para saber qué va en cada columna. '
            .'BÓRRELA antes de cargar el archivo al sistema; si la deja, se reportará como 1 fila '
            .'fallida (no afecta a los demás registros). Ver hoja "Instrucciones" para el detalle completo.'
        );

        $sheet->freezePane('A3');
    }

    /**
     * @param  array<int, array<int, string>>  $rows  primera fila es el encabezado
     * @param  string[]  $notes
     */
    public function addInstructionsSheet(Spreadsheet $spreadsheet, array $rows, array $notes): void
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
    public function addReferenceSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
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
