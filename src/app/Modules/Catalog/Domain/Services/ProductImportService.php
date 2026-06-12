<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductImportService
{
    /**
     * @return array{total: int, success: int, failed: int, errors: array}
     */
    public function import(array $rows): array
    {
        $results = [
            'total'   => count($rows),
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $validator = Validator::make($row, [
                    'name'                => 'required|string|max:255',
                    'code'                => 'required|string|max:50|unique:products,code',
                    'sku'                 => 'nullable|string|max:100|unique:products,sku',
                    'category_code'       => 'required|string|exists:categories,code',
                    'unit_abbreviation'   => 'required|string|exists:units_of_measure,abbreviation',
                    'classification_code' => 'nullable|string|exists:product_classifications,code',
                ], [
                    'name.required'              => 'El nombre es obligatorio.',
                    'code.required'               => 'El código es obligatorio.',
                    'code.unique'                  => 'Ya existe un producto con este código.',
                    'sku.unique'                   => 'Ya existe un producto con este SKU.',
                    'category_code.required'      => 'El código de categoría es obligatorio.',
                    'category_code.exists'        => 'No existe ninguna categoría con este código. Revise la hoja "Categorías" de la plantilla.',
                    'unit_abbreviation.required'  => 'La abreviatura de unidad de medida es obligatoria.',
                    'unit_abbreviation.exists'    => 'No existe ninguna unidad de medida con esta abreviatura. Revise la hoja "Unidades de medida" de la plantilla.',
                    'classification_code.exists'  => 'No existe ninguna clasificación con este código. Revise la hoja "Clasificaciones" de la plantilla.',
                ]);

                if ($validator->fails()) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row'    => $rowNumber,
                        'errors' => $validator->errors()->toArray(),
                    ];
                    continue;
                }

                $category = CategoryModel::where('code', $row['category_code'])->first();
                $unit = UnitOfMeasureModel::where('abbreviation', $row['unit_abbreviation'])->first();
                $classification = ! empty($row['classification_code'])
                    ? ProductClassificationModel::where('code', $row['classification_code'])->first()
                    : null;

                DB::transaction(function () use ($row, $category, $unit, $classification) {
                    ProductModel::create([
                        'name'                => $row['name'],
                        'code'                => $row['code'],
                        'sku'                 => $row['sku'] ?? null,
                        'category_id'         => $category->id,
                        'base_unit_id'        => $unit->id,
                        'classification_id'   => $classification?->id,
                        'product_type'        => ProductType::Simple->value,
                        'description'         => $row['description'] ?? null,
                        'requires_cold_chain' => filter_var($row['requires_cold_chain'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'reorder_point'       => (int) ($row['reorder_point'] ?? 0),
                        'reorder_quantity'    => (int) ($row['reorder_quantity'] ?? 0),
                        'min_stock'           => (int) ($row['min_stock'] ?? 0),
                        'max_stock'           => (int) ($row['max_stock'] ?? 0),
                        'is_active'           => true,
                    ]);
                });

                $results['success']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row'    => $rowNumber,
                    'errors' => ['exception' => [$e->getMessage()]],
                ];
            }
        }

        return $results;
    }
}
