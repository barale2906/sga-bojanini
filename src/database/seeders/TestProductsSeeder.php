<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductKitComponentModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Database\Seeder;

/**
 * Fixtures de productos solo para el entorno de testing.
 *
 * Nunca se ejecuta en producción ni staging — el guard al inicio lo garantiza.
 * Es llamado automáticamente por CatalogSeeder cuando app()->environment('testing').
 *
 * Depende de que CatalogSeeder haya creado previamente las presentaciones,
 * unidades de medida y categorías (UND, KIT, CJ-M5000, CJ-500, PQ-100, INS-MED).
 */
class TestProductsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $und     = UnitOfMeasureModel::where('abbreviation', 'UND')->first();
        $kitUnit = UnitOfMeasureModel::where('abbreviation', 'KIT')->first();
        $cat     = CategoryModel::where('code', 'INS-MED')->first();
        $dmClass = ProductClassificationModel::where('code', 'DM')->first();
        $otrClass = ProductClassificationModel::where('code', 'OTR')->first();
        $master   = ProductPresentationModel::where('code', 'CJ-M5000')->first();
        $cajaPres = ProductPresentationModel::where('code', 'CJ-500')->first();
        $paqPres  = ProductPresentationModel::where('code', 'PQ-100')->first();

        // ── Aguja ─────────────────────────────────────────────────────────────
        $aguja = ProductModel::firstOrCreate(
            ['code' => 'AGU-21G'],
            [
                'category_id'         => $cat?->id,
                'classification_id'   => $dmClass?->id,
                'base_unit_id'        => $und?->id,
                'product_type'        => ProductType::Simple->value,
                'name'                => 'Aguja 21G',
                'description'         => 'Aguja hipodérmica 21G',
                'risk_level'          => 'Clase I',
                'lab_brand'           => 'BD Becton Dickinson',
                'requires_cold_chain' => false,
                'is_active'           => true,
            ]
        );

        if ($master && $cajaPres && $paqPres) {
            $aguja->presentations()->syncWithoutDetaching([
                $master->id   => ['is_purchase_default' => false, 'sort_order' => 1],
                $cajaPres->id => ['is_purchase_default' => false, 'sort_order' => 2],
                $paqPres->id  => ['is_purchase_default' => true,  'sort_order' => 3],
            ]);
        }

        // ── Gasa ──────────────────────────────────────────────────────────────
        $gasa = ProductModel::firstOrCreate(
            ['code' => 'GAS-10X10'],
            [
                'category_id'      => $cat?->id,
                'classification_id'=> $dmClass?->id,
                'base_unit_id'     => $und?->id,
                'product_type'     => ProductType::Simple->value,
                'name'             => 'Gasa estéril 10x10',
                'risk_level'       => 'Clase I',
                'is_active'        => true,
            ]
        );

        if ($cajaPres && $paqPres) {
            $gasa->presentations()->syncWithoutDetaching([
                $cajaPres->id => ['is_purchase_default' => true,  'sort_order' => 1],
                $paqPres->id  => ['is_purchase_default' => false, 'sort_order' => 2],
            ]);
        }

        // ── Kit de cirugía básica ──────────────────────────────────────────────
        $kit = ProductModel::firstOrCreate(
            ['code' => 'KIT-CIR-BAS'],
            [
                'category_id'      => $cat?->id,
                'classification_id'=> $otrClass?->id,
                'base_unit_id'     => $kitUnit?->id,
                'product_type'     => ProductType::Kit->value,
                'name'             => 'Paquete cirugía básica',
                'description'      => 'Kit de insumos para cirugía menor',
                'is_active'        => true,
            ]
        );

        ProductKitComponentModel::firstOrCreate(
            ['kit_product_id' => $kit->id, 'component_product_id' => $gasa->id],
            ['quantity_per_kit' => 5, 'sort_order' => 1]
        );

        ProductKitComponentModel::firstOrCreate(
            ['kit_product_id' => $kit->id, 'component_product_id' => $aguja->id],
            ['quantity_per_kit' => 10, 'sort_order' => 2]
        );
    }
}
