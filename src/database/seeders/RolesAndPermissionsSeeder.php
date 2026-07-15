<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder que crea los roles, permisos y el usuario admin inicial.
 *
 * Ejecutar: php artisan db:seed --class=RolesAndPermissionsSeeder
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar caché de permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ─────────────────────────────────────────
        // PASO 1: Crear TODOS los permisos
        // ─────────────────────────────────────────
        $permissions = [
            // Usuarios
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
            // Roles
            'roles.ver', 'roles.crear', 'roles.editar', 'roles.eliminar',
            // Almacenes
            'almacenes.ver', 'almacenes.crear', 'almacenes.editar', 'almacenes.eliminar', 'almacenes.asignar',
            // Zonas
            'zonas.ver', 'zonas.crear', 'zonas.editar', 'zonas.eliminar',
            // Ubicaciones
            'ubicaciones.ver', 'ubicaciones.crear', 'ubicaciones.editar', 'ubicaciones.eliminar',
            // Productos genéricos
            'generic-products.ver', 'generic-products.crear', 'generic-products.editar',
            'generic-products.eliminar', 'generic-products.importar', 'generic-products.barcode',
            // Variantes de producto
            'product-variants.ver', 'product-variants.crear', 'product-variants.editar', 'product-variants.eliminar',
            // Proveedores
            'proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar', 'proveedores.importar',
            // Lotes
            'lotes.ver', 'lotes.crear',
            // Stock
            'stock.ver',
            // Movimientos
            'movimientos.entrada', 'movimientos.salida', 'movimientos.transferir',
            'movimientos.ajuste', 'movimientos.devolucion', 'movimientos.baja', 'movimientos.importar',
            'movimientos.confirmar', 'movimientos.cancelar',
            // Órdenes de compra
            'ordenes_compra.ver', 'ordenes_compra.crear', 'ordenes_compra.aprobar',
            'ordenes_compra.enviar', 'ordenes_compra.recibir',
            // Sensores
            'sensores.ver', 'sensores.crear', 'sensores.editar', 'sensores.eliminar', 'sensores.asignar',
            // Lecturas
            'lecturas.ver', 'lecturas.crear',
            // Reglas de alerta
            'reglas_alerta.ver', 'reglas_alerta.crear', 'reglas_alerta.editar', 'reglas_alerta.eliminar',
            // Auditoría
            'auditoria.ver', 'auditoria.exportar',
            // Reportes
            'reportes.ver', 'reportes.exportar',
            // Integraciones
            'integraciones.ver', 'integraciones.configurar',
            // Consumos
            'consumos.ver', 'consumos.crear',
            // Tablero
            'tablero.ver',
            // Notificaciones
            'notificaciones.ver',
            // Centros de costo
            'centros_costo.ver', 'centros_costo.crear', 'centros_costo.editar', 'centros_costo.eliminar',
            // Servicios médicos
            'servicios_medicos.ver', 'servicios_medicos.crear', 'servicios_medicos.editar', 'servicios_medicos.eliminar', 'servicios_medicos.importar',
            // Tarifas de procedimientos
            'procedimientos.ver', 'procedimientos.crear', 'procedimientos.editar', 'procedimientos.eliminar',
            // Registros de procedimientos por paciente
            'registros_procedimientos.ver', 'registros_procedimientos.crear', 'registros_procedimientos.editar', 'registros_procedimientos.eliminar',
            // Plantillas de evolución clínica
            'plantillas_clinicas.ver', 'plantillas_clinicas.crear', 'plantillas_clinicas.editar', 'plantillas_clinicas.eliminar',
            // Evoluciones clínicas de pacientes
            'evoluciones_clinicas.ver', 'evoluciones_clinicas.crear', 'evoluciones_clinicas.editar', 'evoluciones_clinicas.eliminar',
            // Medicamentos aplicados por procedimiento (solo lectura — se extraen del movimiento de inventario)
            'medicamentos_procedimiento.ver',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ─────────────────────────────────────────
        // PASO 2: Crear los 7 roles y asignar permisos
        // ─────────────────────────────────────────

        // 1. Super Administrador — TIENE TODOS LOS PERMISOS
        $superAdmin = Role::firstOrCreate(['name' => 'super_administrador', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Administrador — Casi todo, excepto configurar integraciones
        $admin = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $admin->givePermissionTo([
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
            'roles.ver', 'roles.crear', 'roles.editar', 'roles.eliminar',
            'almacenes.ver', 'almacenes.crear', 'almacenes.editar', 'almacenes.eliminar', 'almacenes.asignar',
            'zonas.ver', 'zonas.crear', 'zonas.editar', 'zonas.eliminar',
            'ubicaciones.ver', 'ubicaciones.crear', 'ubicaciones.editar', 'ubicaciones.eliminar',
            'generic-products.ver', 'generic-products.crear', 'generic-products.editar',
            'generic-products.eliminar', 'generic-products.importar', 'generic-products.barcode',
            'product-variants.ver', 'product-variants.crear', 'product-variants.editar', 'product-variants.eliminar',
            'proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar', 'proveedores.importar',
            'lotes.ver', 'lotes.crear',
            'stock.ver',
            'movimientos.entrada', 'movimientos.salida', 'movimientos.transferir',
            'movimientos.ajuste', 'movimientos.devolucion', 'movimientos.baja', 'movimientos.importar',
            'movimientos.confirmar', 'movimientos.cancelar',
            'ordenes_compra.ver', 'ordenes_compra.crear', 'ordenes_compra.aprobar',
            'ordenes_compra.enviar', 'ordenes_compra.recibir',
            'sensores.ver', 'sensores.crear', 'sensores.editar', 'sensores.eliminar', 'sensores.asignar',
            'lecturas.ver', 'lecturas.crear',
            'reglas_alerta.ver', 'reglas_alerta.crear', 'reglas_alerta.editar', 'reglas_alerta.eliminar',
            'auditoria.ver', 'auditoria.exportar',
            'reportes.ver', 'reportes.exportar',
            'integraciones.ver', 'integraciones.configurar',
            'consumos.ver', 'consumos.crear',
            'tablero.ver',
            'notificaciones.ver',
            'centros_costo.ver', 'centros_costo.crear', 'centros_costo.editar', 'centros_costo.eliminar',
            'servicios_medicos.ver', 'servicios_medicos.crear', 'servicios_medicos.editar', 'servicios_medicos.eliminar',
            'procedimientos.ver', 'procedimientos.crear', 'procedimientos.editar', 'procedimientos.eliminar',
            'registros_procedimientos.ver', 'registros_procedimientos.crear', 'registros_procedimientos.editar', 'registros_procedimientos.eliminar',
            'plantillas_clinicas.ver', 'plantillas_clinicas.crear', 'plantillas_clinicas.editar', 'plantillas_clinicas.eliminar',
            'evoluciones_clinicas.ver', 'evoluciones_clinicas.crear', 'evoluciones_clinicas.editar', 'evoluciones_clinicas.eliminar',
            'medicamentos_procedimiento.ver',
        ]);

        // 3. Operador de Almacén — Gestiona inventario día a día
        $warehouseOperator = Role::firstOrCreate(['name' => 'operador_almacen', 'guard_name' => 'web']);
        $warehouseOperator->givePermissionTo([
            'almacenes.ver',
            'zonas.ver',
            'ubicaciones.ver',
            'generic-products.ver', 'product-variants.ver',
            'proveedores.ver',
            'lotes.ver', 'lotes.crear',
            'stock.ver',
            'movimientos.entrada', 'movimientos.salida', 'movimientos.transferir',
            'movimientos.confirmar', 'movimientos.cancelar',
            'lecturas.ver', 'lecturas.crear',
            'tablero.ver',
            'notificaciones.ver',
            'centros_costo.ver',
            'servicios_medicos.ver',
            'procedimientos.ver',
            'registros_procedimientos.ver', 'registros_procedimientos.crear',
            'plantillas_clinicas.ver',
            'evoluciones_clinicas.ver', 'evoluciones_clinicas.crear',
            'medicamentos_procedimiento.ver',
        ]);

        // 4. Compras — Gestiona órdenes de compra
        $purchasing = Role::firstOrCreate(['name' => 'compras', 'guard_name' => 'web']);
        $purchasing->givePermissionTo([
            'generic-products.ver', 'product-variants.ver',
            'proveedores.ver', 'proveedores.crear', 'proveedores.editar',
            'stock.ver',
            'ordenes_compra.ver', 'ordenes_compra.crear', 'ordenes_compra.enviar',
            'tablero.ver',
            'notificaciones.ver',
            'reportes.ver',
        ]);

        // 5. Auditor — Solo lectura y exportación
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
        $auditor->givePermissionTo([
            'almacenes.ver', 'zonas.ver', 'ubicaciones.ver',
            'generic-products.ver', 'product-variants.ver', 'proveedores.ver',
            'lotes.ver', 'stock.ver',
            'sensores.ver', 'lecturas.ver',
            'auditoria.ver', 'auditoria.exportar',
            'reportes.ver', 'reportes.exportar',
            'tablero.ver',
        ]);

        // 6. Jefe de Almacén — Operador + aprobaciones + ajustes
        $warehouseManager = Role::firstOrCreate(['name' => 'jefe_almacen', 'guard_name' => 'web']);
        $warehouseManager->givePermissionTo([
            'almacenes.ver', 'almacenes.crear', 'almacenes.editar',
            'zonas.ver', 'zonas.crear', 'zonas.editar',
            'ubicaciones.ver', 'ubicaciones.crear', 'ubicaciones.editar',
            'generic-products.ver', 'generic-products.crear', 'generic-products.editar', 'generic-products.importar',
            'generic-products.barcode',
            'product-variants.ver', 'product-variants.crear', 'product-variants.editar',
            'proveedores.ver',
            'lotes.ver', 'lotes.crear',
            'stock.ver',
            'movimientos.entrada', 'movimientos.salida', 'movimientos.transferir',
            'movimientos.ajuste', 'movimientos.devolucion', 'movimientos.baja', 'movimientos.importar',
            'movimientos.confirmar', 'movimientos.cancelar',
            'ordenes_compra.ver', 'ordenes_compra.aprobar', 'ordenes_compra.recibir',
            'sensores.ver', 'sensores.crear', 'sensores.editar',
            'lecturas.ver', 'lecturas.crear',
            'reglas_alerta.ver', 'reglas_alerta.crear', 'reglas_alerta.editar',
            'reportes.ver', 'reportes.exportar',
            'tablero.ver',
            'notificaciones.ver',
            'centros_costo.ver', 'centros_costo.crear', 'centros_costo.editar',
            'servicios_medicos.ver', 'servicios_medicos.crear', 'servicios_medicos.editar',
            'procedimientos.ver', 'procedimientos.crear', 'procedimientos.editar',
            'registros_procedimientos.ver', 'registros_procedimientos.crear', 'registros_procedimientos.editar',
            'plantillas_clinicas.ver', 'plantillas_clinicas.crear', 'plantillas_clinicas.editar',
            'evoluciones_clinicas.ver', 'evoluciones_clinicas.crear', 'evoluciones_clinicas.editar',
            'medicamentos_procedimiento.ver',
        ]);

        // 7. Personal Médico — Solo ve stock y registra consumos
        $medicalStaff = Role::firstOrCreate(['name' => 'personal_medico', 'guard_name' => 'web']);
        $medicalStaff->givePermissionTo([
            'generic-products.ver', 'product-variants.ver',
            'stock.ver',
            'movimientos.salida',
            'consumos.ver', 'consumos.crear',
            'tablero.ver',
            'notificaciones.ver',
            'centros_costo.ver',
            'servicios_medicos.ver',
            'procedimientos.ver',
            'registros_procedimientos.ver', 'registros_procedimientos.crear',
            'plantillas_clinicas.ver',
            'evoluciones_clinicas.ver', 'evoluciones_clinicas.crear',
            'medicamentos_procedimiento.ver',
        ]);

        // ─────────────────────────────────────────
        // PASO 3: Crear usuario admin inicial
        // ─────────────────────────────────────────

        $abv = UserModel::firstOrCreate(
            ['email' => 'alexanderbarajas@gmail.com'],
            [
                'name' => 'Ing. Alexander Barajas',
                'password' => bcrypt('10203040'),
                'phone' => null,
                'is_active' => true,
            ],
        );
        $abv->assignRole('super_administrador');

        $this->command->info('Roles, permisos y usuario admin creados exitosamente.');
        $this->command->info('  Email: admin@sga.bojanini.com');
        $this->command->info('  Password: Admin2026!');
        $this->command->info('  Rol: super_administrador');
    }
}
