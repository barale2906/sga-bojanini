<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductSanitaryRegistrationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
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
                    'category_code'       => 'required|string|exists:categories,code',
                    'unit_abbreviation'   => 'required|string|exists:units_of_measure,abbreviation',
                    'classification_code' => 'nullable|string|exists:product_classifications,code',
                    'volume_cm3'          => 'nullable|numeric|min:0',
                    'weight_kg'           => 'nullable|numeric|min:0',
                    'concentration'       => 'nullable|string|max:100',
                    'risk_level'          => 'nullable|string|max:100',
                    'lab_brand'           => 'nullable|string|max:255',
                    'pharmaceutical_form'      => 'nullable|string|max:150',
                    'commercial_presentation'  => 'nullable|string|max:150',
                    'serie_reference'          => 'nullable|string|max:150',
                    'useful_life'              => 'nullable|string|max:100',
                    'sanitary_registration'    => 'nullable|string|max:100',
                ], [
                    'name.required'              => 'El nombre es obligatorio.',
                    'category_code.required'      => 'El código de categoría es obligatorio.',
                    'category_code.exists'        => 'No existe ninguna categoría con este código. Revise la hoja "Categorías" de la plantilla.',
                    'unit_abbreviation.required'  => 'La abreviatura de unidad de medida es obligatoria.',
                    'unit_abbreviation.exists'    => 'No existe ninguna unidad de medida con esta abreviatura. Revise la hoja "Unidades de medida" de la plantilla.',
                    'classification_code.exists'  => 'No existe ninguna clasificación con este código. Revise la hoja "Clasificaciones" de la plantilla.',
                    'volume_cm3.numeric'           => 'El volumen debe ser un número.',
                    'weight_kg.numeric'            => 'El peso debe ser un número.',
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
                    $maxBarcode = (int) GenericProductModel::max('barcode');
                    $barcode    = str_pad((string) ($maxBarcode + 1), 6, '0', STR_PAD_LEFT);

                    $generic = GenericProductModel::create([
                        'name'                => $row['name'],
                        'barcode'             => $barcode,
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
                        'volume_cm3'          => ! empty($row['volume_cm3']) ? (float) $row['volume_cm3'] : null,
                        'weight_kg'           => ! empty($row['weight_kg']) ? (float) $row['weight_kg'] : null,
                        'concentration'       => ! empty($row['concentration']) ? $row['concentration'] : null,
                        'pharmaceutical_form' => ! empty($row['pharmaceutical_form']) ? $row['pharmaceutical_form'] : null,
                        'is_active'           => true,
                    ]);

                    $variant = ProductVariantModel::create([
                        'generic_product_id'      => $generic->id,
                        'lab_brand'               => ! empty($row['lab_brand']) ? $row['lab_brand'] : null,
                        'brand_sku'               => ! empty($row['sku']) ? (string) $row['sku'] : null,
                        'risk_level'              => ! empty($row['risk_level']) ? $row['risk_level'] : null,
                        'commercial_presentation' => ! empty($row['commercial_presentation']) ? $row['commercial_presentation'] : null,
                        'serie_reference'         => ! empty($row['serie_reference']) ? $row['serie_reference'] : null,
                        'useful_life'             => ! empty($row['useful_life']) ? $row['useful_life'] : null,
                        'is_active'               => true,
                    ]);

                    if (! empty($row['sanitary_registration'])) {
                        ProductSanitaryRegistrationModel::create([
                            'product_variant_id'  => $variant->id,
                            'registration_number' => $row['sanitary_registration'],
                            'expiry_date'         => '2099-12-31',
                            'is_active'           => true,
                        ]);
                    }
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
