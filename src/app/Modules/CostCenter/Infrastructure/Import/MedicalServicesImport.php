<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Import;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MedicalServicesImport implements SkipsEmptyRows, ToArray, WithHeadingRow
{
    private array $rows = [];

    public function array(array $rows): void
    {
        $this->rows = array_map(
            fn (array $row) => array_map(
                fn ($value) => is_string($value) ? trim($value) : $value,
                $row,
            ),
            $rows,
        );
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}
