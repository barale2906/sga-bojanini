<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Import;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Lee la hoja "Entradas" de la plantilla de inventario inicial
 * (`InitialEntryTemplateBuilder`) y la deja disponible como array de filas
 * para `ImportInitialEntriesUseCase`.
 */
class InitialEntriesImport implements ToArray, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets
{
    private const DATE_COLUMNS    = ['expiration_date', 'manufacturing_date'];

    private const NUMERIC_COLUMNS = ['quantity', 'entry_temperature'];

    private array $rows = [];

    /** Callback de Maatwebsite: recibe las filas ya parseadas de la hoja "Entradas". */
    public function array(array $rows): void
    {
        $this->rows = array_map(fn (array $row) => $this->normalizeRow($row), $rows);
    }

    /** @return array<int, array<string, mixed>> filas normalizadas, listas para validar/procesar */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Solo procesa la primera hoja ("Entradas"); las hojas de referencia
     * (Instrucciones, Productos, Almacén y zona destino) se ignoran.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /**
     * Normaliza cada celda de la fila:
     * - Columnas de fecha con serial numérico de Excel → 'Y-m-d'.
     * - Columnas numéricas (quantity, entry_temperature) → se dejan como están.
     * - Todo lo demás (códigos, lotes, facturas, notas) → string recortado,
     *   incluso si Excel entregó un número entero (p.ej. lote "20250603").
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (in_array($key, self::DATE_COLUMNS, true) && is_numeric($value)) {
                $row[$key] = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                continue;
            }

            if (in_array($key, self::NUMERIC_COLUMNS, true)) {
                continue;
            }

            $row[$key] = trim((string) $value);
        }

        return $row;
    }
}
