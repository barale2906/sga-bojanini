<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use Illuminate\Support\Facades\Validator;

class ProductClassificationImportService
{
    /**
     * Crea clasificaciones de producto nuevas a partir de la hoja
     * "Clasificaciones" de la plantilla. Las filas cuyo `code` ya existe en
     * la base de datos se omiten (no se consideran error, ya que esa hoja
     * también se entrega como referencia).
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

            if (! empty($row['code']) && ProductClassificationModel::where('code', $row['code'])->exists()) {
                $results['skipped']++;
                continue;
            }

            $validator = Validator::make($row, [
                'code'        => 'required|string|max:20',
                'name'        => 'required|string|max:100',
                'description' => 'nullable|string',
            ], [
                'code.required' => 'El código de la clasificación es obligatorio.',
                'name.required' => 'El nombre de la clasificación es obligatorio.',
            ]);

            if ($validator->fails()) {
                $results['failed']++;
                $results['errors'][] = [
                    'row'    => $rowNumber,
                    'errors' => $validator->errors()->toArray(),
                ];
                continue;
            }

            ProductClassificationModel::create([
                'code'                      => $row['code'],
                'name'                      => $row['name'],
                'description'               => $row['description'] ?? null,
                'has_sanitary_registration' => filter_var($row['has_sanitary_registration'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'has_concentration'         => filter_var($row['has_concentration'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'has_risk_level'            => filter_var($row['has_risk_level'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'has_pharma_fields'         => filter_var($row['has_pharma_fields'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'has_device_fields'         => filter_var($row['has_device_fields'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'has_lab_brand'             => filter_var($row['has_lab_brand'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'is_active'                 => true,
            ]);

            $results['created']++;
        }

        return $results;
    }
}
