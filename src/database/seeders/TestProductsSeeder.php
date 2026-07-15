<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductKitComponentModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Database\Seeder;

/**
 * Fixtures de productos para el entorno de testing.
 * Llamado automáticamente por CatalogSeeder cuando app()->environment('testing').
 * Depende de que CatalogSeeder haya creado presentaciones, unidades y categorías.
 */
class TestProductsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $und      = UnitOfMeasureModel::where('abbreviation', 'UND')->first();
        $kitUnit  = UnitOfMeasureModel::where('abbreviation', 'KIT')->first();
        $cat      = CategoryModel::where('code', 'INS-MED')->first();
        $dmClass  = ProductClassificationModel::where('code', 'DM')->first();
        $otrClass = ProductClassificationModel::where('code', 'OTR')->first();
        $master   = ProductPresentationModel::where('code', 'CJ-M5000')->first();
        $cajaPres = ProductPresentationModel::where('code', 'CJ-500')->first();
        $paqPres  = ProductPresentationModel::where('code', 'PQ-100')->first();

        // ── Aguja 21G (genérico) ──────────────────────────────────────────────
        $aguja = GenericProductModel::firstOrCreate(
            ['barcode' => '000001'],
            [
                'category_id'         => $cat?->id,
                'classification_id'   => $dmClass?->id,
                'base_unit_id'        => $und?->id,
                'product_type'        => ProductType::Simple->value,
                'name'                => 'Aguja 21G',
                'description'         => 'Aguja hipodérmica 21G',
                'requires_cold_chain' => false,
                'reorder_point'       => 500,
                'min_stock'           => 100,
                'is_active'           => true,
            ]
        );

        ProductVariantModel::firstOrCreate(
            ['generic_product_id' => $aguja->id, 'lab_brand' => 'BD Becton Dickinson'],
            [
                'brand_sku' => 'BD-AGU-21G',
                'is_active' => true,
            ]
        );

        if ($master && $cajaPres && $paqPres) {
            $aguja->presentations()->syncWithoutDetaching([
                $master->id   => ['is_purchase_default' => false, 'sort_order' => 1],
                $cajaPres->id => ['is_purchase_default' => false, 'sort_order' => 2],
                $paqPres->id  => ['is_purchase_default' => true,  'sort_order' => 3],
            ]);
        }

        // ── Gasa estéril 10x10 (genérico) ────────────────────────────────────
        $gasa = GenericProductModel::firstOrCreate(
            ['barcode' => '000002'],
            [
                'category_id'       => $cat?->id,
                'classification_id' => $dmClass?->id,
                'base_unit_id'      => $und?->id,
                'product_type'      => ProductType::Simple->value,
                'name'              => 'Gasa estéril 10x10',
                'reorder_point'     => 200,
                'min_stock'         => 50,
                'is_active'         => true,
            ]
        );

        ProductVariantModel::firstOrCreate(
            ['generic_product_id' => $gasa->id, 'lab_brand' => 'Suavetex'],
            [
                'brand_sku' => 'SVT-GAS-10',
                'is_active' => true,
            ]
        );

        if ($cajaPres && $paqPres) {
            $gasa->presentations()->syncWithoutDetaching([
                $cajaPres->id => ['is_purchase_default' => true,  'sort_order' => 1],
                $paqPres->id  => ['is_purchase_default' => false, 'sort_order' => 2],
            ]);
        }

        // ── Paquete cirugía básica (kit genérico) ─────────────────────────────
        $kit = GenericProductModel::firstOrCreate(
            ['barcode' => '000003'],
            [
                'category_id'       => $cat?->id,
                'classification_id' => $otrClass?->id,
                'base_unit_id'      => $kitUnit?->id,
                'product_type'      => ProductType::Kit->value,
                'name'              => 'Paquete cirugía básica',
                'description'       => 'Kit de insumos para cirugía menor',
                'is_active'         => true,
            ]
        );

        ProductVariantModel::firstOrCreate(
            ['generic_product_id' => $kit->id, 'lab_brand' => 'Genérico'],
            ['is_active' => true]
        );

        ProductKitComponentModel::firstOrCreate(
            ['kit_generic_id' => $kit->id, 'component_generic_id' => $gasa->id],
            ['quantity_per_kit' => 5, 'sort_order' => 1]
        );

        ProductKitComponentModel::firstOrCreate(
            ['kit_generic_id' => $kit->id, 'component_generic_id' => $aguja->id],
            ['quantity_per_kit' => 10, 'sort_order' => 2]
        );
    }
}
