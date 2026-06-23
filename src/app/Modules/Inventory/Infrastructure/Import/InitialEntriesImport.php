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
    private const DATE_COLUMNS = ['expiration_date', 'manufacturing_date'];

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
     * Si el usuario digita la fecha y Excel la convierte automáticamente a
     * un valor de fecha nativo, Maatwebsite la entrega como número serial
     * en vez de texto. Se normaliza a `Y-m-d` para que la validación
     * `date` y el resto del flujo siempre reciban un string consistente.
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = trim($value);

                continue;
            }

            if (in_array($key, self::DATE_COLUMNS, true) && is_numeric($value)) {
                $row[$key] = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }
        }

        return $row;
    }
}
