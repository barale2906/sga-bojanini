<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use Illuminate\Database\Seeder;

/**
 * Categorías base del módulo de activos (idempotente).
 *
 * Ejecutar: php artisan db:seed --class=AssetSeeder
 */
class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'BIO', 'name' => 'Equipo biomédico', 'asset_type' => AssetType::Biomedical, 'description' => 'Equipos médicos con hoja de vida y mantenimiento.'],
            ['code' => 'COM', 'name' => 'Equipo de cómputo', 'asset_type' => AssetType::Computing, 'description' => 'Computadores, portátiles y servidores.'],
            ['code' => 'PER', 'name' => 'Periféricos e impresoras', 'asset_type' => AssetType::Computing, 'description' => 'Impresoras, escáneres y periféricos con mantenimiento.'],
            ['code' => 'MUE', 'name' => 'Mobiliario', 'asset_type' => AssetType::General, 'description' => 'Escritorios, sillas, camillas no eléctricas, archivadores.'],
            ['code' => 'ELE', 'name' => 'Electrodomésticos y TV', 'asset_type' => AssetType::General, 'description' => 'Televisores, neveras de uso no médico, microondas.'],
        ];

        foreach ($categories as $category) {
            AssetCategoryModel::firstOrCreate(['code' => $category['code']], $category);
        }
    }
}
