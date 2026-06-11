<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Application\Services\MenuBuilder;
use Tests\TestCase;

class MenuBuilderTest extends TestCase
{
    private MenuBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new MenuBuilder();
    }

    // ─── Sin permisos ─────────────────────────────────────────────────────────

    public function test_sin_permisos_retorna_menu_vacio(): void
    {
        $menu = $this->builder->build([]);

        $this->assertEmpty($menu);
    }

    // ─── Estructura de cada ítem ──────────────────────────────────────────────

    public function test_cada_item_tiene_los_campos_requeridos(): void
    {
        $menu = $this->builder->build(['tablero.ver']);

        $this->assertCount(1, $menu);
        $item = $menu[0];

        $this->assertArrayHasKey('key',        $item);
        $this->assertArrayHasKey('label',      $item);
        $this->assertArrayHasKey('icon',       $item);
        $this->assertArrayHasKey('route',      $item);
        $this->assertArrayHasKey('permission', $item);
        $this->assertArrayHasKey('actions',    $item);
        $this->assertArrayHasKey('children',   $item);
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function test_tablero_visible_con_permiso_correcto(): void
    {
        $menu = $this->builder->build(['tablero.ver']);

        $this->assertCount(1, $menu);
        $this->assertSame('dashboard', $menu[0]['key']);
    }

    public function test_tablero_no_visible_sin_permiso(): void
    {
        $menu = $this->builder->build(['stock.ver']);

        $keys = array_column($menu, 'key');
        $this->assertNotContains('dashboard', $keys);
    }

    // ─── Grupo Inventario (Catálogo, Almacén, Inventario) ─────────────────────

    public function test_grupo_inventario_visible_con_permiso_productos(): void
    {
        $menu = $this->builder->build(['productos.ver']);

        $keys = array_column($menu, 'key');
        $this->assertContains('inventory-management', $keys);
    }

    public function test_grupo_inventario_no_visible_sin_permisos(): void
    {
        // ninguno de los permisos de catalog/warehouse/inventory
        $menu = $this->builder->build(['tablero.ver']);

        $keys = array_column($menu, 'key');
        $this->assertNotContains('inventory-management', $keys);
    }

    public function test_grupo_inventario_contiene_catalogo_almacen_e_inventario_en_orden(): void
    {
        $menu = $this->builder->build(['productos.ver', 'almacenes.ver', 'stock.ver']);

        $group     = $this->findByKey($menu, 'inventory-management');
        $childKeys = array_column($group['children'], 'key');

        $this->assertSame(['catalog', 'warehouse', 'inventory'], $childKeys);
    }

    public function test_grupo_inventario_solo_incluye_secciones_visibles(): void
    {
        $menu = $this->builder->build(['productos.ver']);

        $group     = $this->findByKey($menu, 'inventory-management');
        $childKeys = array_column($group['children'], 'key');

        $this->assertSame(['catalog'], $childKeys);
    }

    // ─── Catálogo ──────────────────────────────────────────────────────────────

    public function test_products_visible_dentro_de_catalogo(): void
    {
        $menu     = $this->builder->build(['productos.ver']);
        $group    = $this->findByKey($menu, 'inventory-management');
        $catalog  = $this->findByKey($group['children'], 'catalog');
        $itemKeys = array_column($catalog['children'], 'key');

        $this->assertContains('products', $itemKeys);
    }

    // ─── Almacén ───────────────────────────────────────────────────────────────

    public function test_seccion_warehouse_no_aparece_si_no_hay_children_visibles(): void
    {
        // warehouse requiere al menos uno de almacenes.ver | zonas.ver | ubicaciones.ver
        $menu  = $this->builder->build(['productos.ver']);
        $group = $this->findByKey($menu, 'inventory-management');

        $childKeys = array_column($group['children'], 'key');
        $this->assertNotContains('warehouse', $childKeys);
    }

    // ─── Inventario — acciones de movimientos ─────────────────────────────────

    public function test_movements_actions_reflejan_permisos_individuales(): void
    {
        $menu      = $this->builder->build(['stock.ver', 'movimientos.salida', 'movimientos.entrada']);
        $group     = $this->findByKey($menu, 'inventory-management');
        $inventory = $this->findByKey($group['children'], 'inventory');
        $movements = $this->findByKey($inventory['children'], 'movements');

        $this->assertTrue($movements['actions']['entry']);
        $this->assertTrue($movements['actions']['exit']);
        $this->assertFalse($movements['actions']['transfer']);
        $this->assertFalse($movements['actions']['adjust']);
    }

    // ─── Grupo Configuración (Centros de Costo, Administración, Integraciones) ─

    public function test_grupo_configuracion_visible_con_permiso_centros_costo(): void
    {
        $menu = $this->builder->build(['centros_costo.ver']);

        $keys = array_column($menu, 'key');
        $this->assertContains('configuration', $keys);
    }

    public function test_grupo_configuracion_no_visible_sin_permisos(): void
    {
        $menu = $this->builder->build(['tablero.ver', 'stock.ver']);

        $keys = array_column($menu, 'key');
        $this->assertNotContains('configuration', $keys);
    }

    public function test_grupo_configuracion_contiene_secciones_en_orden(): void
    {
        $menu = $this->builder->build([
            'centros_costo.ver',
            'usuarios.ver',
            'consumos.ver',
        ]);

        $group     = $this->findByKey($menu, 'configuration');
        $childKeys = array_column($group['children'], 'key');

        $this->assertSame(['cost-center', 'admin', 'integration'], $childKeys);
    }

    // ─── Centros de Costo ─────────────────────────────────────────────────────

    public function test_seccion_cost_center_visible_con_permiso_ver(): void
    {
        $menu        = $this->builder->build(['centros_costo.ver']);
        $group       = $this->findByKey($menu, 'configuration');
        $sectionKeys = array_column($group['children'], 'key');

        $this->assertContains('cost-center', $sectionKeys);
    }

    public function test_cost_centers_child_visible_con_permiso_ver(): void
    {
        $menu      = $this->builder->build(['centros_costo.ver']);
        $group     = $this->findByKey($menu, 'configuration');
        $section   = $this->findByKey($group['children'], 'cost-center');
        $childKeys = array_column($section['children'], 'key');

        $this->assertContains('cost-centers', $childKeys);
    }

    public function test_medical_services_child_visible_con_permiso_ver(): void
    {
        $menu      = $this->builder->build(['servicios_medicos.ver']);
        $group     = $this->findByKey($menu, 'configuration');
        $section   = $this->findByKey($group['children'], 'cost-center');
        $childKeys = array_column($section['children'], 'key');

        $this->assertContains('medical-services', $childKeys);
    }

    public function test_cost_centers_actions_todos_false_sin_permisos_crud(): void
    {
        $menu    = $this->builder->build(['centros_costo.ver']);
        $group   = $this->findByKey($menu, 'configuration');
        $section = $this->findByKey($group['children'], 'cost-center');
        $item    = $this->findByKey($section['children'], 'cost-centers');

        $this->assertFalse($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    public function test_cost_centers_actions_true_con_permisos_crud(): void
    {
        $menu = $this->builder->build([
            'centros_costo.ver',
            'centros_costo.crear',
            'centros_costo.editar',
            'centros_costo.eliminar',
        ]);
        $group   = $this->findByKey($menu, 'configuration');
        $section = $this->findByKey($group['children'], 'cost-center');
        $item    = $this->findByKey($section['children'], 'cost-centers');

        $this->assertTrue($item['actions']['create']);
        $this->assertTrue($item['actions']['edit']);
        $this->assertTrue($item['actions']['delete']);
    }

    public function test_medical_services_actions_parciales(): void
    {
        $menu = $this->builder->build([
            'servicios_medicos.ver',
            'servicios_medicos.crear',
            // sin editar ni eliminar
        ]);
        $group   = $this->findByKey($menu, 'configuration');
        $section = $this->findByKey($group['children'], 'cost-center');
        $item    = $this->findByKey($section['children'], 'medical-services');

        $this->assertTrue($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    public function test_procedures_child_visible_con_permiso_ver(): void
    {
        $menu      = $this->builder->build(['procedimientos.ver']);
        $group     = $this->findByKey($menu, 'configuration');
        $section   = $this->findByKey($group['children'], 'cost-center');
        $childKeys = array_column($section['children'], 'key');

        $this->assertContains('procedures', $childKeys);
    }

    public function test_procedures_actions_parciales(): void
    {
        $menu = $this->builder->build([
            'procedimientos.ver',
            'procedimientos.crear',
            // sin editar ni eliminar
        ]);
        $group   = $this->findByKey($menu, 'configuration');
        $section = $this->findByKey($group['children'], 'cost-center');
        $item    = $this->findByKey($section['children'], 'procedures');

        $this->assertTrue($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    public function test_patient_procedure_records_child_visible_con_permiso_ver(): void
    {
        $menu      = $this->builder->build(['registros_procedimientos.ver']);
        $group     = $this->findByKey($menu, 'configuration');
        $section   = $this->findByKey($group['children'], 'cost-center');
        $childKeys = array_column($section['children'], 'key');

        $this->assertContains('patient-procedure-records', $childKeys);
    }

    public function test_patient_procedure_records_actions_parciales(): void
    {
        $menu = $this->builder->build([
            'registros_procedimientos.ver',
            'registros_procedimientos.crear',
            // sin editar ni eliminar
        ]);
        $group   = $this->findByKey($menu, 'configuration');
        $section = $this->findByKey($group['children'], 'cost-center');
        $item    = $this->findByKey($section['children'], 'patient-procedure-records');

        $this->assertTrue($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    public function test_solo_permiso_services_sin_centers_genera_seccion_con_un_hijo(): void
    {
        $menu    = $this->builder->build(['servicios_medicos.ver']);
        $group   = $this->findByKey($menu, 'configuration');
        $section = $this->findByKey($group['children'], 'cost-center');

        $this->assertNotNull($section);
        $this->assertCount(1, $section['children']);
        $this->assertSame('medical-services', $section['children'][0]['key']);
    }

    // ─── Grupo Gestión (Reportes, Auditoría) ──────────────────────────────────

    public function test_grupo_gestion_visible_con_permiso_reportes(): void
    {
        $menu = $this->builder->build(['reportes.ver']);

        $keys = array_column($menu, 'key');
        $this->assertContains('management', $keys);

        $group     = $this->findByKey($menu, 'management');
        $childKeys = array_column($group['children'], 'key');
        $this->assertSame(['reports'], $childKeys);
    }

    public function test_grupo_gestion_visible_con_permiso_auditoria(): void
    {
        $menu = $this->builder->build(['auditoria.ver']);

        $group     = $this->findByKey($menu, 'management');
        $childKeys = array_column($group['children'], 'key');
        $this->assertSame(['audit'], $childKeys);
    }

    public function test_grupo_gestion_no_visible_sin_permisos(): void
    {
        $menu = $this->builder->build(['tablero.ver']);

        $keys = array_column($menu, 'key');
        $this->assertNotContains('management', $keys);
    }

    public function test_grupo_gestion_contiene_reportes_y_auditoria_en_orden(): void
    {
        $menu = $this->builder->build(['reportes.ver', 'auditoria.ver']);

        $group     = $this->findByKey($menu, 'management');
        $childKeys = array_column($group['children'], 'key');

        $this->assertSame(['reports', 'audit'], $childKeys);
    }

    public function test_auditor_puede_exportar_reportes(): void
    {
        $menu = $this->builder->build(['reportes.ver', 'reportes.exportar']);

        $group   = $this->findByKey($menu, 'management');
        $reports = $this->findByKey($group['children'], 'reports');

        $this->assertTrue($reports['actions']['export']);
    }

    // ─── Super administrador ─────────────────────────────────────────────────

    public function test_todos_los_permisos_generan_seis_secciones(): void
    {
        $menu = $this->builder->build($this->allPermissions());

        $this->assertCount(6, $menu);

        $keys = array_column($menu, 'key');
        $this->assertContains('dashboard',             $keys);
        $this->assertContains('inventory-management',  $keys);
        $this->assertContains('purchasing',             $keys);
        $this->assertContains('monitoring',             $keys);
        $this->assertContains('management',             $keys);
        $this->assertContains('configuration',          $keys);

        $inventoryGroup = $this->findByKey($menu, 'inventory-management');
        $inventoryKeys  = array_column($inventoryGroup['children'], 'key');
        $this->assertContains('catalog',   $inventoryKeys);
        $this->assertContains('warehouse', $inventoryKeys);
        $this->assertContains('inventory', $inventoryKeys);

        $configGroup = $this->findByKey($menu, 'configuration');
        $configKeys  = array_column($configGroup['children'], 'key');
        $this->assertContains('cost-center', $configKeys);
        $this->assertContains('admin',       $configKeys);
        $this->assertContains('integration', $configKeys);

        $managementGroup = $this->findByKey($menu, 'management');
        $managementKeys  = array_column($managementGroup['children'], 'key');
        $this->assertContains('reports', $managementKeys);
        $this->assertContains('audit',   $managementKeys);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function findByKey(array $items, string $key): ?array
    {
        foreach ($items as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    /** @return string[] */
    private function allPermissions(): array
    {
        return [
            'tablero.ver',
            'almacenes.ver', 'almacenes.crear', 'almacenes.editar', 'almacenes.eliminar',
            'zonas.ver', 'zonas.crear', 'zonas.editar', 'zonas.eliminar',
            'ubicaciones.ver', 'ubicaciones.crear', 'ubicaciones.editar', 'ubicaciones.eliminar',
            'productos.ver', 'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.importar',
            'proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar', 'proveedores.importar',
            'lotes.ver', 'lotes.crear',
            'stock.ver',
            'movimientos.entrada', 'movimientos.salida', 'movimientos.transferir',
            'movimientos.ajuste', 'movimientos.devolucion', 'movimientos.baja',
            'centros_costo.ver', 'centros_costo.crear', 'centros_costo.editar', 'centros_costo.eliminar',
            'servicios_medicos.ver', 'servicios_medicos.crear', 'servicios_medicos.editar', 'servicios_medicos.eliminar',
            'procedimientos.ver', 'procedimientos.crear', 'procedimientos.editar', 'procedimientos.eliminar',
            'registros_procedimientos.ver', 'registros_procedimientos.crear', 'registros_procedimientos.editar', 'registros_procedimientos.eliminar',
            'ordenes_compra.ver', 'ordenes_compra.crear', 'ordenes_compra.aprobar',
            'ordenes_compra.enviar', 'ordenes_compra.recibir',
            'sensores.ver', 'sensores.crear', 'sensores.editar', 'sensores.eliminar',
            'lecturas.ver', 'lecturas.crear',
            'reglas_alerta.ver', 'reglas_alerta.crear', 'reglas_alerta.editar', 'reglas_alerta.eliminar',
            'auditoria.ver', 'auditoria.exportar',
            'reportes.ver', 'reportes.exportar',
            'integraciones.ver', 'integraciones.configurar',
            'consumos.ver', 'consumos.crear',
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
            'roles.ver', 'roles.crear', 'roles.editar', 'roles.eliminar',
        ];
    }
}
