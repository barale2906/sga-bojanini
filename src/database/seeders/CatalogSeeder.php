<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductKitComponentModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // ── Unidades de medida ────────────────────────────────────────────────
        $und = UnitOfMeasureModel::firstOrCreate(
            ['abbreviation' => 'UND'],
            ['name' => 'Unidad', 'is_base' => true, 'is_active' => true]
        );
        $kitUnit = UnitOfMeasureModel::firstOrCreate(
            ['abbreviation' => 'KIT'],
            ['name' => 'Kit', 'is_base' => true, 'is_active' => true]
        );
        $caja = UnitOfMeasureModel::firstOrCreate(
            ['abbreviation' => 'CJ'],
            ['name' => 'Caja', 'is_base' => false, 'is_active' => true]
        );
        $paq = UnitOfMeasureModel::firstOrCreate(
            ['abbreviation' => 'PQ'],
            ['name' => 'Paquete', 'is_base' => false, 'is_active' => true]
        );

        // ── Categoría ─────────────────────────────────────────────────────────
        $cat = CategoryModel::firstOrCreate(
            ['code' => 'INS-MED'],
            ['name' => 'Insumos Médicos', 'is_active' => true]
        );

        // ── Presentaciones compartidas (independientes de producto) ───────────
        $master = ProductPresentationModel::firstOrCreate(
            ['code' => 'CJ-M5000'],
            [
                'name'                => 'Caja Maestra x5000',
                'units_of_measure_id' => $caja->id,
                'factor_to_base'      => 5000,
                'level'               => 1,
                'sort_order'          => 1,
            ]
        );

        $cajaPres = ProductPresentationModel::firstOrCreate(
            ['code' => 'CJ-500'],
            [
                'parent_id'           => $master->id,
                'name'                => 'Caja x500',
                'units_of_measure_id' => $caja->id,
                'quantity_per_parent' => 10,
                'factor_to_base'      => 500,
                'level'               => 2,
                'sort_order'          => 2,
            ]
        );

        $paqPres = ProductPresentationModel::firstOrCreate(
            ['code' => 'PQ-100'],
            [
                'parent_id'           => $cajaPres->id,
                'name'                => 'Paquete x100',
                'units_of_measure_id' => $paq->id,
                'quantity_per_parent' => 5,
                'factor_to_base'      => 100,
                'level'               => 3,
                'sort_order'          => 3,
            ]
        );

        // ── Productos ─────────────────────────────────────────────────────────
        $aguja = ProductModel::firstOrCreate(
            ['code' => 'AGU-21G'],
            [
                'category_id'         => $cat->id,
                'base_unit_id'        => $und->id,
                'product_type'        => ProductType::Simple->value,
                'name'                => 'Aguja 21G',
                'description'         => 'Aguja hipodérmica 21G',
                'requires_cold_chain' => false,
                'is_active'           => true,
            ]
        );

        // Asignar presentaciones al producto Aguja (M:N)
        $aguja->presentations()->syncWithoutDetaching([
            $master->id   => ['is_purchase_default' => false, 'sort_order' => 1],
            $cajaPres->id => ['is_purchase_default' => false, 'sort_order' => 2],
            $paqPres->id  => ['is_purchase_default' => true,  'sort_order' => 3],
        ]);

        $gasa = ProductModel::firstOrCreate(
            ['code' => 'GAS-10X10'],
            [
                'category_id'  => $cat->id,
                'base_unit_id' => $und->id,
                'product_type' => ProductType::Simple->value,
                'name'         => 'Gasa estéril 10x10',
                'is_active'    => true,
            ]
        );

        // La gasa reutiliza las mismas presentaciones que la aguja
        $gasa->presentations()->syncWithoutDetaching([
            $cajaPres->id => ['is_purchase_default' => true,  'sort_order' => 1],
            $paqPres->id  => ['is_purchase_default' => false, 'sort_order' => 2],
        ]);

        // ── Kit ───────────────────────────────────────────────────────────────
        $kit = ProductModel::firstOrCreate(
            ['code' => 'KIT-CIR-BAS'],
            [
                'category_id'  => $cat->id,
                'base_unit_id' => $kitUnit->id,
                'product_type' => ProductType::Kit->value,
                'name'         => 'Paquete cirugía básica',
                'description'  => 'Kit de insumos para cirugía menor',
                'is_active'    => true,
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
