<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Support\Facades\Validator;

class UnitOfMeasureImportService
{
    /**
     * Crea unidades de medida nuevas a partir de la hoja "Unidades de medida"
     * de la plantilla. Las filas cuya `abbreviation` ya existe en la base de
     * datos se omiten (no se consideran error, ya que esa hoja también se
     * entrega como referencia).
     *
     * @return array{total: int, created: int, skipped: int, failed: int, errors: array}
     */
    public function import(array $rows): array
    {
        $results = [
            'total'   => count($rows),
            'created' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if (! empty($row['abbreviation']) && UnitOfMeasureModel::where('abbreviation', $row['abbreviation'])->exists()) {
                $results['skipped']++;
                continue;
            }

            $validator = Validator::make($row, [
                'abbreviation' => 'required|string|max:10',
                'name'         => 'required|string|max:100',
            ], [
                'abbreviation.required' => 'La abreviatura de la unidad de medida es obligatoria.',
                'name.required'         => 'El nombre de la unidad de medida es obligatorio.',
            ]);

            if ($validator->fails()) {
                $results['failed']++;
                $results['errors'][] = [
                    'row'    => $rowNumber,
                    'errors' => $validator->errors()->toArray(),
                ];
                continue;
            }

            UnitOfMeasureModel::create([
                'abbreviation' => $row['abbreviation'],
                'name'         => $row['name'],
                'is_base'      => filter_var($row['is_base'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_active'    => true,
            ]);

            $results['created']++;
        }

        return $results;
    }
}
