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

    // ─── Centros de Costo ─────────────────────────────────────────────────────

    public function test_seccion_cost_center_visible_con_permiso_ver(): void
    {
        $menu = $this->builder->build(['centros_costo.ver']);

        $keys = array_column($menu, 'key');
        $this->assertContains('cost-center', $keys);
    }

    public function test_seccion_cost_center_no_visible_sin_permisos(): void
    {
        $menu = $this->builder->build(['tablero.ver', 'stock.ver']);

        $keys = array_column($menu, 'key');
        $this->assertNotContains('cost-center', $keys);
    }

    public function test_cost_centers_child_visible_con_permiso_ver(): void
    {
        $menu     = $this->builder->build(['centros_costo.ver']);
        $section  = $this->findByKey($menu, 'cost-center');
        $childKeys = array_column($section['children'], 'key');

        $this->assertContains('cost-centers', $childKeys);
    }

    public function test_medical_services_child_visible_con_permiso_ver(): void
    {
        $menu     = $this->builder->build(['servicios_medicos.ver']);
        $section  = $this->findByKey($menu, 'cost-center');
        $childKeys = array_column($section['children'], 'key');

        $this->assertContains('medical-services', $childKeys);
    }

    public function test_cost_centers_actions_todos_false_sin_permisos_crud(): void
    {
        $menu    = $this->builder->build(['centros_costo.ver']);
        $section = $this->findByKey($menu, 'cost-center');
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
        $section = $this->findByKey($menu, 'cost-center');
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
        $section = $this->findByKey($menu, 'cost-center');
        $item    = $this->findByKey($section['children'], 'medical-services');

        $this->assertTrue($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    public function test_solo_permiso_services_sin_centers_genera_seccion_con_un_hijo(): void
    {
        $menu    = $this->builder->build(['servicios_medicos.ver']);
        $section = $this->findByKey($menu, 'cost-center');

        $this->assertNotNull($section);
        $this->assertCount(1, $section['children']);
        $this->assertSame('medical-services', $section['children'][0]['key']);
    }

    // ─── Inventario — acciones de movimientos ─────────────────────────────────

    public function test_movements_actions_reflejan_permisos_individuales(): void
    {
        $menu      = $this->builder->build(['stock.ver', 'movimientos.salida', 'movimientos.entrada']);
        $inventory = $this->findByKey($menu, 'inventory');
        $movements = $this->findByKey($inventory['children'], 'movements');

        $this->assertTrue($movements['actions']['entry']);
        $this->assertTrue($movements['actions']['exit']);
        $this->assertFalse($movements['actions']['transfer']);
        $this->assertFalse($movements['actions']['adjust']);
    }

    // ─── Secciones padre sin children no se incluyen ─────────────────────────

    public function test_seccion_padre_no_aparece_si_no_hay_children_visibles(): void
    {
        // warehouse requiere al menos uno de almacenes.ver | zonas.ver | ubicaciones.ver
        $menu = $this->builder->build(['tablero.ver']);
        $keys = array_column($menu, 'key');

        $this->assertNotContains('warehouse', $keys);
    }

    // ─── Super administrador ─────────────────────────────────────────────────

    public function test_todos_los_permisos_generan_once_secciones(): void
    {
        $menu = $this->builder->build($this->allPermissions());

        $this->assertCount(11, $menu);

        $keys = array_column($menu, 'key');
        $this->assertContains('dashboard',   $keys);
        $this->assertContains('warehouse',   $keys);
        $this->assertContains('catalog',     $keys);
        $this->assertContains('inventory',   $keys);
        $this->assertContains('cost-center', $keys);
        $this->assertContains('purchasing',  $keys);
        $this->assertContains('monitoring',  $keys);
        $this->assertContains('integration', $keys);
        $this->assertContains('reports',     $keys);
        $this->assertContains('audit',       $keys);
        $this->assertContains('admin',       $keys);
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
