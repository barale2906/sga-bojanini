<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // ── Clasificaciones de producto ───────────────────────────────────────
        $this->call(ProductClassificationSeeder::class);

        $dmClass  = ProductClassificationModel::where('code', 'DM')->first();
        $otrClass = ProductClassificationModel::where('code', 'OTR')->first();

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

        if (app()->environment('testing')) {
            $this->call(TestProductsSeeder::class);
        }
    }
}
