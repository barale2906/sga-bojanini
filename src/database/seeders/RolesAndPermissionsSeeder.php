<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─────────────────────────────────────────
        // PASO 1: Crear TODOS los permisos
        // ─────────────────────────────────────────
        $permissions = [
            // Usuarios
            'users.view', 'users.create', 'users.update', 'users.delete',
            // Roles
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            // Almacenes
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            // Zonas
            'zones.view', 'zones.create', 'zones.update', 'zones.delete',
            // Ubicaciones
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            // Productos
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.import',
            // Proveedores
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete', 'suppliers.import',
            // Lotes
            'batches.view', 'batches.create',
            // Stock
            'stock.view',
            // Movimientos
            'movements.entry', 'movements.exit', 'movements.transfer',
            'movements.adjustment', 'movements.return', 'movements.write_off',
            // Órdenes de compra
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.approve',
            'purchase_orders.send', 'purchase_orders.receive',
            // Sensores
            'sensors.view', 'sensors.create', 'sensors.update', 'sensors.delete',
            // Lecturas
            'readings.view', 'readings.create',
            // Reglas de alerta
            'alert_rules.view', 'alert_rules.create', 'alert_rules.update', 'alert_rules.delete',
            // Auditoría
            'audit.view', 'audit.export',
            // Reportes
            'reports.view', 'reports.export',
            // Integraciones
            'integrations.view', 'integrations.configure',
            // Consumos
            'consumptions.view', 'consumptions.create',
            // Dashboard
            'dashboard.view',
            // Notificaciones
            'notifications.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ─────────────────────────────────────────
        // PASO 2: Crear los 7 roles y asignar permisos
        // ─────────────────────────────────────────

        // 1. Super Admin — TIENE TODOS LOS PERMISOS
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. Admin — Casi todo, excepto configurar integraciones
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo([
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'zones.view', 'zones.create', 'zones.update', 'zones.delete',
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.import',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete', 'suppliers.import',
            'batches.view', 'batches.create',
            'stock.view',
            'movements.entry', 'movements.exit', 'movements.transfer',
            'movements.adjustment', 'movements.return', 'movements.write_off',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.approve',
            'purchase_orders.send', 'purchase_orders.receive',
            'sensors.view', 'sensors.create', 'sensors.update', 'sensors.delete',
            'readings.view', 'readings.create',
            'alert_rules.view', 'alert_rules.create', 'alert_rules.update', 'alert_rules.delete',
            'audit.view', 'audit.export',
            'reports.view', 'reports.export',
            'integrations.view', 'integrations.configure',
            'consumptions.view', 'consumptions.create',
            'dashboard.view',
            'notifications.view',
        ]);

        // 3. Operador de Almacén — Gestiona inventario día a día
        $warehouseOperator = Role::firstOrCreate(['name' => 'warehouse_operator', 'guard_name' => 'web']);
        $warehouseOperator->givePermissionTo([
            'warehouses.view',
            'zones.view',
            'locations.view',
            'products.view',
            'suppliers.view',
            'batches.view', 'batches.create',
            'stock.view',
            'movements.entry', 'movements.exit', 'movements.transfer',
            'readings.view', 'readings.create',
            'dashboard.view',
            'notifications.view',
        ]);

        // 4. Compras — Gestiona órdenes de compra
        $purchasing = Role::firstOrCreate(['name' => 'purchasing', 'guard_name' => 'web']);
        $purchasing->givePermissionTo([
            'products.view',
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'stock.view',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.send',
            'dashboard.view',
            'notifications.view',
            'reports.view',
        ]);

        // 5. Auditor — Solo lectura y exportación
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
        $auditor->givePermissionTo([
            'warehouses.view', 'zones.view', 'locations.view',
            'products.view', 'suppliers.view',
            'batches.view', 'stock.view',
            'sensors.view', 'readings.view',
            'audit.view', 'audit.export',
            'reports.view', 'reports.export',
            'dashboard.view',
        ]);

        // 6. Jefe de Almacén — Operador + aprobaciones + ajustes
        $warehouseManager = Role::firstOrCreate(['name' => 'warehouse_manager', 'guard_name' => 'web']);
        $warehouseManager->givePermissionTo([
            'warehouses.view', 'warehouses.create', 'warehouses.update',
            'zones.view', 'zones.create', 'zones.update',
            'locations.view', 'locations.create', 'locations.update',
            'products.view', 'products.create', 'products.update', 'products.import',
            'suppliers.view',
            'batches.view', 'batches.create',
            'stock.view',
            'movements.entry', 'movements.exit', 'movements.transfer',
            'movements.adjustment', 'movements.return', 'movements.write_off',
            'purchase_orders.view', 'purchase_orders.approve', 'purchase_orders.receive',
            'sensors.view', 'sensors.create', 'sensors.update',
            'readings.view', 'readings.create',
            'alert_rules.view', 'alert_rules.create', 'alert_rules.update',
            'reports.view', 'reports.export',
            'dashboard.view',
            'notifications.view',
        ]);

        // 7. Personal Médico — Solo ve stock y registra consumos
        $medicalStaff = Role::firstOrCreate(['name' => 'medical_staff', 'guard_name' => 'web']);
        $medicalStaff->givePermissionTo([
            'products.view',
            'stock.view',
            'consumptions.view', 'consumptions.create',
            'dashboard.view',
            'notifications.view',
        ]);

        // ─────────────────────────────────────────
        // PASO 3: Crear usuario admin inicial
        // ─────────────────────────────────────────
        $adminUser = UserModel::firstOrCreate(
            ['email' => 'alexanderbarajas@gmail.com'],
            [
                'name'      => 'Administrador SGA',
                'password'  => '10203040',
                'phone'     => null,
                'is_active' => true,
            ],
        );
        $adminUser->assignRole('super_admin');

        $this->command->info('Roles, permisos y usuario admin creados exitosamente.');
        $this->command->info('  Email: alexanderbarajas@gmail.com');
        $this->command->info('  Password: 10203040');
        $this->command->info('  Rol: super_admin');
    }
}
