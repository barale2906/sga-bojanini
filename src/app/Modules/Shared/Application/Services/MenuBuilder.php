<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

/**
 * Construye el árbol de navegación del sistema filtrando por los permisos
 * del usuario autenticado.
 *
 * Cada entrada de menú define:
 *  - key        : identificador único estable (para el router del frontend)
 *  - label      : etiqueta visible
 *  - icon       : nombre del ícono (Heroicons / Lucide — acordar con frontend)
 *  - route      : nombre de ruta del frontend
 *  - permission : permiso mínimo para ver el ítem; null = siempre visible
 *  - actions    : mapa de acciones disponibles según permisos del usuario
 *  - children   : sub-ítems (solo en secciones de primer nivel)
 *
 * Una sección de primer nivel se incluye si al menos uno de sus hijos
 * es visible para el usuario.
 */
class MenuBuilder
{
    /**
     * @param  string[]  $userPermissions  Lista de permisos del usuario autenticado.
     * @return array<int, array<string, mixed>>
     */
    public function build(array $userPermissions): array
    {
        $has = fn (string $perm): bool => in_array($perm, $userPermissions, true);

        $menu = [];

        // ── Tablero ───────────────────────────────────────────────────────────
        if ($has('tablero.ver')) {
            $menu[] = [
                'key'        => 'dashboard',
                'label'      => 'Tablero',
                'icon'       => 'layout-dashboard',
                'route'      => 'dashboard',
                'permission' => 'tablero.ver',
                'actions'    => [],
                'children'   => [],
            ];
        }

        // ── Almacén ───────────────────────────────────────────────────────────
        $warehouseChildren = array_filter([
            $has('almacenes.ver') ? [
                'key'        => 'warehouses',
                'label'      => 'Almacenes',
                'icon'       => 'building-2',
                'route'      => 'warehouses.index',
                'permission' => 'almacenes.ver',
                'actions'    => $this->crudActions($has, 'almacenes'),
            ] : null,
            $has('zonas.ver') ? [
                'key'        => 'zones',
                'label'      => 'Zonas',
                'icon'       => 'grid-2x2',
                'route'      => 'zones.index',
                'permission' => 'zonas.ver',
                'actions'    => $this->crudActions($has, 'zonas'),
            ] : null,
            $has('ubicaciones.ver') ? [
                'key'        => 'locations',
                'label'      => 'Ubicaciones',
                'icon'       => 'map-pin',
                'route'      => 'locations.index',
                'permission' => 'ubicaciones.ver',
                'actions'    => $this->crudActions($has, 'ubicaciones'),
            ] : null,
        ]);

        if (! empty($warehouseChildren)) {
            $menu[] = [
                'key'        => 'warehouse',
                'label'      => 'Almacén',
                'icon'       => 'building-2',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($warehouseChildren),
            ];
        }

        // ── Catálogo ──────────────────────────────────────────────────────────
        $catalogChildren = array_filter([
            $has('productos.ver') ? [
                'key'        => 'products',
                'label'      => 'Productos',
                'icon'       => 'package',
                'route'      => 'products.index',
                'permission' => 'productos.ver',
                'actions'    => array_merge(
                    $this->crudActions($has, 'productos'),
                    ['import' => $has('productos.importar')],
                ),
            ] : null,
            $has('productos.ver') ? [
                'key'        => 'categories',
                'label'      => 'Categorías',
                'icon'       => 'tag',
                'route'      => 'categories.index',
                'permission' => 'productos.ver',
                'actions'    => $this->crudActions($has, 'productos'),
            ] : null,
            $has('productos.ver') ? [
                'key'        => 'units-of-measure',
                'label'      => 'Unidades de medida',
                'icon'       => 'ruler',
                'route'      => 'units-of-measure.index',
                'permission' => 'productos.ver',
                'actions'    => $this->crudActions($has, 'productos'),
            ] : null,
            $has('productos.ver') ? [
                'key'        => 'product-classifications',
                'label'      => 'Clasificaciones',
                'icon'       => 'layers',
                'route'      => 'product-classifications.index',
                'permission' => 'productos.ver',
                'actions'    => $this->crudActions($has, 'productos'),
            ] : null,
            $has('proveedores.ver') ? [
                'key'        => 'suppliers',
                'label'      => 'Proveedores',
                'icon'       => 'truck',
                'route'      => 'suppliers.index',
                'permission' => 'proveedores.ver',
                'actions'    => array_merge(
                    $this->crudActions($has, 'proveedores'),
                    ['import' => $has('proveedores.importar')],
                ),
            ] : null,
        ]);

        if (! empty($catalogChildren)) {
            $menu[] = [
                'key'        => 'catalog',
                'label'      => 'Catálogo',
                'icon'       => 'book-open',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($catalogChildren),
            ];
        }

        // ── Inventario ────────────────────────────────────────────────────────
        $inventoryChildren = array_filter([
            $has('stock.ver') ? [
                'key'        => 'stock',
                'label'      => 'Stock actual',
                'icon'       => 'boxes',
                'route'      => 'stock.index',
                'permission' => 'stock.ver',
                'actions'    => [],
            ] : null,
            $has('lotes.ver') ? [
                'key'        => 'batches',
                'label'      => 'Lotes',
                'icon'       => 'calendar-range',
                'route'      => 'batches.index',
                'permission' => 'lotes.ver',
                'actions'    => ['create' => $has('lotes.crear')],
            ] : null,
            $has('stock.ver') ? [
                'key'        => 'movements',
                'label'      => 'Movimientos',
                'icon'       => 'arrow-left-right',
                'route'      => 'movements.index',
                'permission' => 'stock.ver',
                'actions'    => [
                    'entry'     => $has('movimientos.entrada'),
                    'exit'      => $has('movimientos.salida'),
                    'transfer'  => $has('movimientos.transferir'),
                    'adjust'    => $has('movimientos.ajuste'),
                    'return'    => $has('movimientos.devolucion'),
                    'write_off' => $has('movimientos.baja'),
                ],
            ] : null,
        ]);

        if (! empty($inventoryChildren)) {
            $menu[] = [
                'key'        => 'inventory',
                'label'      => 'Inventario',
                'icon'       => 'warehouse',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($inventoryChildren),
            ];
        }

        // ── Centros de Costo ──────────────────────────────────────────────────
        $costCenterChildren = array_filter([
            $has('centros_costo.ver') ? [
                'key'        => 'cost-centers',
                'label'      => 'Centros de costo',
                'icon'       => 'landmark',
                'route'      => 'cost-centers.index',
                'permission' => 'centros_costo.ver',
                'actions'    => $this->crudActions($has, 'centros_costo'),
            ] : null,
            $has('servicios_medicos.ver') ? [
                'key'        => 'medical-services',
                'label'      => 'Servicios médicos',
                'icon'       => 'stethoscope',
                'route'      => 'medical-services.index',
                'permission' => 'servicios_medicos.ver',
                'actions'    => $this->crudActions($has, 'servicios_medicos'),
            ] : null,
        ]);

        if (! empty($costCenterChildren)) {
            $menu[] = [
                'key'        => 'cost-center',
                'label'      => 'Centros de Costo',
                'icon'       => 'landmark',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($costCenterChildren),
            ];
        }

        // ── Compras ───────────────────────────────────────────────────────────
        if ($has('ordenes_compra.ver')) {
            $menu[] = [
                'key'        => 'purchasing',
                'label'      => 'Compras',
                'icon'       => 'shopping-cart',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => [
                    [
                        'key'        => 'purchase-orders',
                        'label'      => 'Órdenes de compra',
                        'icon'       => 'file-text',
                        'route'      => 'purchase-orders.index',
                        'permission' => 'ordenes_compra.ver',
                        'actions'    => [
                            'create'  => $has('ordenes_compra.crear'),
                            'approve' => $has('ordenes_compra.aprobar'),
                            'send'    => $has('ordenes_compra.enviar'),
                            'receive' => $has('ordenes_compra.recibir'),
                        ],
                    ],
                ],
            ];
        }

        // ── Monitoreo ─────────────────────────────────────────────────────────
        $monitoringChildren = array_filter([
            $has('sensores.ver') ? [
                'key'        => 'sensors',
                'label'      => 'Sensores',
                'icon'       => 'thermometer',
                'route'      => 'sensors.index',
                'permission' => 'sensores.ver',
                'actions'    => $this->crudActions($has, 'sensores'),
            ] : null,
            $has('lecturas.ver') ? [
                'key'        => 'sensor-readings',
                'label'      => 'Lecturas',
                'icon'       => 'activity',
                'route'      => 'sensor-readings.index',
                'permission' => 'lecturas.ver',
                'actions'    => ['create' => $has('lecturas.crear')],
            ] : null,
            $has('reglas_alerta.ver') ? [
                'key'        => 'alert-rules',
                'label'      => 'Reglas de alerta',
                'icon'       => 'bell',
                'route'      => 'alert-rules.index',
                'permission' => 'reglas_alerta.ver',
                'actions'    => $this->crudActions($has, 'reglas_alerta'),
            ] : null,
        ]);

        if (! empty($monitoringChildren)) {
            $menu[] = [
                'key'        => 'monitoring',
                'label'      => 'Monitoreo',
                'icon'       => 'activity',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($monitoringChildren),
            ];
        }

        // ── Integraciones ─────────────────────────────────────────────────────
        $integrationChildren = array_filter([
            $has('consumos.ver') ? [
                'key'        => 'consumptions',
                'label'      => 'Consumos clínicos',
                'icon'       => 'heart-pulse',
                'route'      => 'consumptions.index',
                'permission' => 'consumos.ver',
                'actions'    => ['create' => $has('consumos.crear')],
            ] : null,
            $has('integraciones.ver') ? [
                'key'        => 'integrations',
                'label'      => 'Integraciones externas',
                'icon'       => 'plug',
                'route'      => 'integrations.index',
                'permission' => 'integraciones.ver',
                'actions'    => ['configure' => $has('integraciones.configurar')],
            ] : null,
        ]);

        if (! empty($integrationChildren)) {
            $menu[] = [
                'key'        => 'integration',
                'label'      => 'Integraciones',
                'icon'       => 'plug',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($integrationChildren),
            ];
        }

        // ── Reportes ──────────────────────────────────────────────────────────
        if ($has('reportes.ver')) {
            $menu[] = [
                'key'        => 'reports',
                'label'      => 'Reportes',
                'icon'       => 'bar-chart-2',
                'route'      => 'reports.index',
                'permission' => 'reportes.ver',
                'actions'    => ['export' => $has('reportes.exportar')],
                'children'   => [],
            ];
        }

        // ── Auditoría ─────────────────────────────────────────────────────────
        if ($has('auditoria.ver')) {
            $menu[] = [
                'key'        => 'audit',
                'label'      => 'Auditoría',
                'icon'       => 'shield-check',
                'route'      => 'audit.index',
                'permission' => 'auditoria.ver',
                'actions'    => ['export' => $has('auditoria.exportar')],
                'children'   => [],
            ];
        }

        // ── Administración ────────────────────────────────────────────────────
        $adminChildren = array_filter([
            $has('usuarios.ver') ? [
                'key'        => 'users',
                'label'      => 'Usuarios',
                'icon'       => 'users',
                'route'      => 'users.index',
                'permission' => 'usuarios.ver',
                'actions'    => $this->crudActions($has, 'usuarios'),
            ] : null,
            $has('roles.ver') ? [
                'key'        => 'roles',
                'label'      => 'Roles y permisos',
                'icon'       => 'shield',
                'route'      => 'roles.index',
                'permission' => 'roles.ver',
                'actions'    => $this->crudActions($has, 'roles'),
            ] : null,
        ]);

        if (! empty($adminChildren)) {
            $menu[] = [
                'key'        => 'admin',
                'label'      => 'Administración',
                'icon'       => 'settings',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($adminChildren),
            ];
        }

        return $menu;
    }

    /**
     * @param  callable(string): bool  $has
     * @return array{create: bool, edit: bool, delete: bool}
     */
    private function crudActions(callable $has, string $prefix): array
    {
        return [
            'create' => $has("{$prefix}.crear"),
            'edit'   => $has("{$prefix}.editar"),
            'delete' => $has("{$prefix}.eliminar"),
        ];
    }
}
