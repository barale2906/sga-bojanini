<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Import;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SuppliersImport implements ToArray, WithHeadingRow
{
    private array $rows = [];

    public function array(array $rows): void
    {
        $this->rows = $rows;
    }

    public function getRows(): array
    {
        return $this->rows;
    }
}
