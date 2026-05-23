<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra todos los permisos del sistema de inglés a español.
 * Formato: modulo.accion  →  modulo_es.accion_es
 */
return new class extends Migration
{
    /** @var array<string, string> Mapa antiguo_nombre => nuevo_nombre */
    private array $renames = [
        // Usuarios
        'users.view'   => 'usuarios.ver',
        'users.create' => 'usuarios.crear',
        'users.update' => 'usuarios.editar',
        'users.delete' => 'usuarios.eliminar',
        // Roles
        'roles.view'   => 'roles.ver',
        'roles.create' => 'roles.crear',
        'roles.update' => 'roles.editar',
        'roles.delete' => 'roles.eliminar',
        // Almacenes
        'warehouses.view'   => 'almacenes.ver',
        'warehouses.create' => 'almacenes.crear',
        'warehouses.update' => 'almacenes.editar',
        'warehouses.delete' => 'almacenes.eliminar',
        // Zonas
        'zones.view'   => 'zonas.ver',
        'zones.create' => 'zonas.crear',
        'zones.update' => 'zonas.editar',
        'zones.delete' => 'zonas.eliminar',
        // Ubicaciones
        'locations.view'   => 'ubicaciones.ver',
        'locations.create' => 'ubicaciones.crear',
        'locations.update' => 'ubicaciones.editar',
        'locations.delete' => 'ubicaciones.eliminar',
        // Productos
        'products.view'   => 'productos.ver',
        'products.create' => 'productos.crear',
        'products.update' => 'productos.editar',
        'products.delete' => 'productos.eliminar',
        'products.import' => 'productos.importar',
        // Proveedores
        'suppliers.view'   => 'proveedores.ver',
        'suppliers.create' => 'proveedores.crear',
        'suppliers.update' => 'proveedores.editar',
        'suppliers.delete' => 'proveedores.eliminar',
        'suppliers.import' => 'proveedores.importar',
        // Lotes
        'batches.view'   => 'lotes.ver',
        'batches.create' => 'lotes.crear',
        // Stock
        'stock.view' => 'stock.ver',
        // Movimientos
        'movements.entry'      => 'movimientos.entrada',
        'movements.exit'       => 'movimientos.salida',
        'movements.transfer'   => 'movimientos.transferir',
        'movements.adjustment' => 'movimientos.ajuste',
        'movements.return'     => 'movimientos.devolucion',
        'movements.write_off'  => 'movimientos.baja',
        // Órdenes de compra
        'purchase_orders.view'    => 'ordenes_compra.ver',
        'purchase_orders.create'  => 'ordenes_compra.crear',
        'purchase_orders.approve' => 'ordenes_compra.aprobar',
        'purchase_orders.send'    => 'ordenes_compra.enviar',
        'purchase_orders.receive' => 'ordenes_compra.recibir',
        // Sensores
        'sensors.view'   => 'sensores.ver',
        'sensors.create' => 'sensores.crear',
        'sensors.update' => 'sensores.editar',
        'sensors.delete' => 'sensores.eliminar',
        // Lecturas
        'readings.view'   => 'lecturas.ver',
        'readings.create' => 'lecturas.crear',
        // Reglas de alerta
        'alert_rules.view'   => 'reglas_alerta.ver',
        'alert_rules.create' => 'reglas_alerta.crear',
        'alert_rules.update' => 'reglas_alerta.editar',
        'alert_rules.delete' => 'reglas_alerta.eliminar',
        // Auditoría
        'audit.view'   => 'auditoria.ver',
        'audit.export' => 'auditoria.exportar',
        // Reportes
        'reports.view'   => 'reportes.ver',
        'reports.export' => 'reportes.exportar',
        // Integraciones
        'integrations.view'      => 'integraciones.ver',
        'integrations.configure' => 'integraciones.configurar',
        // Consumos
        'consumptions.view'   => 'consumos.ver',
        'consumptions.create' => 'consumos.crear',
        // Tablero
        'dashboard.view' => 'tablero.ver',
        // Notificaciones
        'notifications.view' => 'notificaciones.ver',
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('permissions')
                ->where('name', $old)
                ->update(['name' => $new]);
        }

        // Limpiar caché de permisos de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('permissions')
                ->where('name', $new)
                ->update(['name' => $old]);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
