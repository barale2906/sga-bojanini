<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Import;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MedicalServicesImport implements SkipsEmptyRows, ToArray, WithHeadingRow
{
    private const STRING_COLUMNS = ['code', 'parent_code'];

    private array $rows = [];

    public function array(array $rows): void
    {
        $this->rows = array_map(
            fn (array $row) => array_combine(
                array_keys($row),
                array_map(
                    fn ($value, $key) => $this->normalizeValue($value, $key),
                    $row,
                    array_keys($row),
                ),
            ),
            $rows,
        );
    }

    private function normalizeValue(mixed $value, int|string $key): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (in_array($key, self::STRING_COLUMNS, true) && is_numeric($value)) {
            return (string) $value;
        }

        return $value;
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}
