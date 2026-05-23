<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra los roles del sistema de inglés a español.
 */
return new class extends Migration
{
    /** @var array<string, string> Mapa antiguo_nombre => nuevo_nombre */
    private array $renames = [
        'super_admin'        => 'super_administrador',
        'admin'              => 'administrador',
        'warehouse_operator' => 'operador_almacen',
        'purchasing'         => 'compras',
        'warehouse_manager'  => 'jefe_almacen',
        'medical_staff'      => 'personal_medico',
        // 'auditor' no cambia
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('roles')
                ->where('name', $old)
                ->update(['name' => $new]);
        }

        // Limpiar caché de permisos de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('roles')
                ->where('name', $new)
                ->update(['name' => $old]);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
