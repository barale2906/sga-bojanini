<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

/**
 * Construye el árbol de navegación del sistema filtrando por los permisos
 * del usuario autenticado.
 *
 * Estructura del árbol (hasta 3 niveles):
 *  - Nivel 1: ítems de primer nivel (Tablero, Inventario, Compras, Monitoreo,
 *    Gestión, Configuración). Algunos son ítems hoja (Tablero, Compras,
 *    Monitoreo) y otros son grupos contenedores (Inventario, Gestión,
 *    Configuración) cuyo `route` es null.
 *  - Nivel 2: dentro de los grupos contenedores, secciones (p. ej. Catálogo,
 *    Almacén, Centros de Costo) o ítems hoja (p. ej. Reportes, Auditoría).
 *  - Nivel 3: dentro de las secciones de nivel 2, los ítems hoja finales.
 *
 * Cada ítem hoja define:
 *  - key        : identificador único estable (para el router del frontend)
 *  - label      : etiqueta visible
 *  - icon       : nombre del ícono (Heroicons / Lucide — acordar con frontend)
 *  - route      : nombre de ruta del frontend
 *  - permission : permiso mínimo para ver el ítem; null = siempre visible
 *  - actions    : mapa de acciones disponibles según permisos del usuario
 *  - children   : sub-ítems (en grupos y secciones)
 *
 * Una sección o grupo se incluye si al menos uno de sus hijos es visible
 * para el usuario.
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

        // ── Inventario (grupo) ───────────────────────────────────────────────
        $catalogSection   = $this->buildCatalogSection($has);
        $warehouseSection = $this->buildWarehouseSection($has);
        $inventorySection = $this->buildInventorySection($has);

        $inventoryGroupChildren = array_filter([
            $catalogSection,
            $warehouseSection,
            $inventorySection,
        ]);

        if (! empty($inventoryGroupChildren)) {
            $menu[] = [
                'key'        => 'inventory-management',
                'label'      => 'Inventario',
                'icon'       => 'package-search',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($inventoryGroupChildren),
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
        $monitoringSection = $this->buildMonitoringSection($has);

        if ($monitoringSection !== null) {
            $menu[] = $monitoringSection;
        }

        // ── Gestión (grupo) ──────────────────────────────────────────────────
        $managementGroupChildren = array_filter([
            $has('reportes.ver') ? [
                'key'        => 'reports',
                'label'      => 'Reportes',
                'icon'       => 'bar-chart-2',
                'route'      => 'reports.index',
                'permission' => 'reportes.ver',
                'actions'    => ['export' => $has('reportes.exportar')],
                'children'   => [],
            ] : null,
            $has('auditoria.ver') ? [
                'key'        => 'audit',
                'label'      => 'Auditoría',
                'icon'       => 'shield-check',
                'route'      => 'audit.index',
                'permission' => 'auditoria.ver',
                'actions'    => ['export' => $has('auditoria.exportar')],
                'children'   => [],
            ] : null,
        ]);

        if (! empty($managementGroupChildren)) {
            $menu[] = [
                'key'        => 'management',
                'label'      => 'Gestión',
                'icon'       => 'briefcase',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($managementGroupChildren),
            ];
        }

        // ── Configuración (grupo) ────────────────────────────────────────────
        $costCenterSection   = $this->buildCostCenterSection($has);
        $adminSection        = $this->buildAdminSection($has);
        $integrationSection  = $this->buildIntegrationSection($has);

        $configurationGroupChildren = array_filter([
            $costCenterSection,
            $adminSection,
            $integrationSection,
        ]);

        if (! empty($configurationGroupChildren)) {
            $menu[] = [
                'key'        => 'configuration',
                'label'      => 'Configuración',
                'icon'       => 'sliders-horizontal',
                'route'      => null,
                'permission' => null,
                'actions'    => [],
                'children'   => array_values($configurationGroupChildren),
            ];
        }

        return $menu;
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildCatalogSection(callable $has): ?array
    {
        $children = array_filter([
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

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'catalog',
            'label'      => 'Catálogo',
            'icon'       => 'book-open',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildWarehouseSection(callable $has): ?array
    {
        $children = array_filter([
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

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'warehouse',
            'label'      => 'Almacén',
            'icon'       => 'building-2',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildInventorySection(callable $has): ?array
    {
        $children = array_filter([
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

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'inventory',
            'label'      => 'Inventario',
            'icon'       => 'warehouse',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildMonitoringSection(callable $has): ?array
    {
        $children = array_filter([
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

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'monitoring',
            'label'      => 'Monitoreo',
            'icon'       => 'activity',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildCostCenterSection(callable $has): ?array
    {
        $children = array_filter([
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
            $has('procedimientos.ver') ? [
                'key'        => 'procedures',
                'label'      => 'Procedimientos',
                'icon'       => 'clipboard-list',
                'route'      => 'procedures.index',
                'permission' => 'procedimientos.ver',
                'actions'    => $this->crudActions($has, 'procedimientos'),
            ] : null,
            $has('registros_procedimientos.ver') ? [
                'key'        => 'patient-procedure-records',
                'label'      => 'Registros de procedimientos',
                'icon'       => 'clipboard-check',
                'route'      => 'patient-procedure-records.index',
                'permission' => 'registros_procedimientos.ver',
                'actions'    => $this->crudActions($has, 'registros_procedimientos'),
            ] : null,
        ]);

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'cost-center',
            'label'      => 'Centros de Costo',
            'icon'       => 'landmark',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildAdminSection(callable $has): ?array
    {
        $children = array_filter([
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

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'admin',
            'label'      => 'Administración',
            'icon'       => 'settings',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
    }

    /**
     * @param  callable(string): bool  $has
     * @return array<string, mixed>|null
     */
    private function buildIntegrationSection(callable $has): ?array
    {
        $children = array_filter([
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

        if (empty($children)) {
            return null;
        }

        return [
            'key'        => 'integration',
            'label'      => 'Integraciones',
            'icon'       => 'plug',
            'route'      => null,
            'permission' => null,
            'actions'    => [],
            'children'   => array_values($children),
        ];
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
