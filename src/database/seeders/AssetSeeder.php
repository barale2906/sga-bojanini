<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos y categorías base del módulo de activos (idempotente).
 *
 * Seguro para producción: solo crea lo que falta del módulo de activos y
 * agrega sus permisos a super_administrador; no toca otros roles ni usuarios.
 *
 * Ejecutar: php artisan db:seed --class=AssetSeeder
 */
class AssetSeeder extends Seeder
{
    /** Permisos del módulo (sin roles propios: se asignan a quien los requiera). */
    public const PERMISSIONS = [
        'activos.ver', 'activos.crear', 'activos.editar', 'activos.eliminar', 'activos.dar_baja',
        'activos.comentar', 'activos.importar', 'activos.exportar',
        'activos_catalogos.crear', 'activos_catalogos.editar', 'activos_catalogos.eliminar',
        'activos_traslados.solicitar', 'activos_traslados.confirmar',
        'activos_mantenimientos.ver', 'activos_mantenimientos.programar',
        'activos_mantenimientos.ejecutar', 'activos_mantenimientos.cancelar',
        'activos_documentos.cargar', 'activos_documentos.eliminar',
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedCategories();
    }

    private function seedPermissions(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super_administrador', 'guard_name' => 'web'])
            ->givePermissionTo(self::PERMISSIONS);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function seedCategories(): void
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
