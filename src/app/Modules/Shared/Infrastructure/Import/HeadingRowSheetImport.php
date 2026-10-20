<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Import;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee una hoja con encabezado en la fila 1 y entrega sus filas como
 * arreglos asociativos normalizados:
 *  - Textos recortados; celdas vacías → null.
 *  - Las columnas indicadas como texto se convierten a string aunque Excel
 *    las entregue como número (códigos, seriales, NIT).
 *
 * Las claves del arreglo resultante conservan la posición original
 * (índice 0 = fila 2 de Excel) para reportar errores con el número de fila.
 */
class HeadingRowSheetImport implements SkipsEmptyRows, ToArray, WithHeadingRow
{
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @param string[] $stringColumns */
    public function __construct(
        private readonly array $stringColumns = [],
    ) {}

    public function array(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $normalized = [];

            foreach ($row as $key => $value) {
                $normalized[$key] = $this->normalize($value, (string) $key);
            }

            $this->rows[$index] = $normalized;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getRows(): array
    {
        return $this->rows;
    }

    private function normalize(mixed $value, string $key): mixed
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_numeric($value) && in_array($key, $this->stringColumns, true)) {
            return is_float($value) && floor($value) === $value ? (string) (int) $value : (string) $value;
        }

        return $value;
    }
}
