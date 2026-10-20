<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetLocationHistoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMovementModel;

/** Registro, edición, estados, comentarios, listados y hoja de vida de activos. */
class AssetTest extends AssetTestCase
{
    public function test_registro_genera_codigo_secuencial_historial_y_bitacora(): void
    {
        $first  = $this->createAsset('BIO', ['comment' => 'Ingresa por compra']);
        $second = $this->createAsset('MUE', ['name' => 'Escritorio']);

        $this->assertSame('000001', $first['code']);
        $this->assertSame('000002', $second['code']);
        $this->assertSame('operational', $first['status']);
        $this->assertSame('Clínica', $first['location']['site_name']);
        $this->assertTrue($first['requires_maintenance']);
        $this->assertFalse($second['requires_maintenance']);

        $this->assertSame(1, AssetLocationHistoryModel::where('asset_id', $first['id'])->whereNull('to_date')->count());
        $this->assertDatabaseHas('asset_movements', ['asset_id' => $first['id'], 'type' => 'registration']);
        $this->assertDatabaseHas('asset_comments', ['asset_id' => $first['id'], 'comment' => 'Ingresa por compra']);
    }

    public function test_no_registra_activo_dado_de_baja_ni_en_sitio_inactivo(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/v1/assets', [
            'name' => 'X', 'category_id' => $this->category('BIO')->id, 'place_id' => $this->place()->id, 'status' => 'retired',
        ])->assertUnprocessable();

        $place = $this->place('Clínica', 'Cerrado');
        $place->update(['is_active' => false]);

        $this->withHeaders($this->auth())->postJson('/api/v1/assets', [
            'name' => 'X', 'category_id' => $this->category('BIO')->id, 'place_id' => $place->id,
        ])->assertStatus(409);
    }

    public function test_editar_no_cambia_ubicacion_ni_estado(): void
    {
        $asset = $this->createAsset();
        $other = $this->place('Edificio 2', 'Sala 3');

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/{$asset['id']}", [
            'name'        => 'Monitor multiparámetro',
            'category_id' => $asset['category']['id'],
            'place_id'    => $other->id,
            'status'      => 'available',
            'brand'       => 'Mindray',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Monitor multiparámetro')
            ->assertJsonPath('data.brand', 'Mindray')
            ->assertJsonPath('data.status', 'operational')
            ->assertJsonPath('data.location.place_id', $asset['location']['place_id']);
    }

    public function test_cambio_de_estado_queda_en_bitacora_y_no_permite_baja_directa(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/status", ['status' => 'out_of_service', 'reason' => 'No enciende'])
            ->assertOk()->assertJsonPath('data.status', 'out_of_service');

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset['id'], 'type' => 'status_change', 'from_status' => 'operational', 'to_status' => 'out_of_service',
        ]);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/status", ['status' => 'retired'])
            ->assertUnprocessable();
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/status", ['status' => 'out_of_service'])
            ->assertStatus(409);
    }

    public function test_comentarios_se_acumulan_con_autor(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/comments", ['comment' => 'Cable de poder desgastado'])->assertCreated();
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/comments", ['comment' => 'Se cambió el cable'])->assertCreated();

        $comments = $this->withHeaders($this->auth())->getJson("/api/v1/assets/{$asset['id']}/comments")->assertOk()->json('data');

        $this->assertCount(2, $comments);
        $this->assertSame('Se cambió el cable', $comments[0]['comment']);
        $this->assertSame($this->admin->name, $comments[0]['user']['name']);
    }

    public function test_comentar_requiere_permiso(): void
    {
        $asset  = $this->createAsset();
        $viewer = $this->userWith(['activos.ver']);

        $this->withHeaders($this->auth($viewer))->postJson("/api/v1/assets/{$asset['id']}/comments", ['comment' => 'x'])->assertForbidden();
    }

    public function test_listado_filtra_por_tipo_sede_y_busqueda(): void
    {
        $this->createAsset('BIO', ['name' => 'Desfibrilador']);
        $this->createAsset('COM', ['name' => 'Portátil Lenovo', 'place_id' => $this->place('Edificio 2', 'Call center')->id]);
        $this->createAsset('MUE', ['name' => 'Silla ergonómica']);

        $this->withHeaders($this->auth())->getJson('/api/v1/assets?asset_type=computing')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Portátil Lenovo');

        $siteId = $this->place('Edificio 2', 'Call center')->site_id;
        $this->withHeaders($this->auth())->getJson("/api/v1/assets?site_id={$siteId}")->assertJsonPath('meta.total', 1);

        $this->withHeaders($this->auth())->getJson('/api/v1/assets?search=desfib')->assertJsonPath('meta.total', 1);
    }

    public function test_estadisticas_por_estado_tipo_y_sede(): void
    {
        $this->createAsset('BIO', ['acquisition_value' => 1000000]);
        $this->createAsset('MUE', ['acquisition_value' => 200000]);

        $stats = $this->withHeaders($this->auth())->getJson('/api/v1/assets/statistics')->assertOk()->json('data');

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['by_status']['operational']);
        $this->assertSame(1, $stats['by_type']['biomedical']);
        $this->assertSame('Clínica', $stats['by_site'][0]['site']);
        $this->assertEquals(1200000, $stats['total_value']);
    }

    public function test_hoja_de_vida_descarta_campos_de_otro_tipo(): void
    {
        $asset = $this->createAsset('COM');

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/{$asset['id']}/technical-profile", [
            'processor'       => 'Intel i5',
            'ip_address'      => '192.168.1.20',
            'invima_registry' => '2020DM-1',
            'licenses'        => [['name' => 'Office 365', 'expires_at' => '2027-01-01']],
        ])->assertOk()
            ->assertJsonPath('data.processor', 'Intel i5')
            ->assertJsonPath('data.invima_registry', null)
            ->assertJsonPath('data.licenses.0.name', 'Office 365');

        $this->withHeaders($this->auth())->getJson("/api/v1/assets/{$asset['id']}")
            ->assertJsonPath('data.technical_profile.processor', 'Intel i5');
    }

    public function test_activo_general_no_admite_hoja_de_vida(): void
    {
        $asset = $this->createAsset('MUE');

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/{$asset['id']}/technical-profile", ['manufacturer' => 'X'])
            ->assertStatus(409);
    }

    public function test_elimina_activo_sin_historia_pero_no_con_traslados(): void
    {
        $asset = $this->createAsset();
        $this->withHeaders($this->auth())->deleteJson("/api/v1/assets/{$asset['id']}")->assertOk();
        $this->assertSoftDeleted('assets', ['id' => $asset['id']]);

        $moved = $this->createAsset();
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$moved['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $this->place('Clínica', 'Consultorio 2')->id,
        ])->assertCreated();

        $this->withHeaders($this->auth())->deleteJson("/api/v1/assets/{$moved['id']}")->assertStatus(409);
    }

    public function test_filtros_entrega_catalogos_y_enumeraciones(): void
    {
        $this->place();

        $data = $this->withHeaders($this->auth())->getJson('/api/v1/assets/filters')->assertOk()->json('data');

        $this->assertNotEmpty($data['categories']);
        $this->assertSame('Consultorio 1', $data['sites'][0]['places'][0]['name']);
        $this->assertContains('external_repair', array_column($data['transfer_types'], 'value'));
        $this->assertNotContains('retired', array_column($data['settable_statuses'], 'value'));
        $this->assertContains($this->admin->name, array_column($data['users'], 'name'));
        $this->assertArrayHasKey('suppliers', $data);
    }

    public function test_bitacora_paginada(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->getJson("/api/v1/assets/{$asset['id']}/movements")
            ->assertOk()->assertJsonPath('meta.total', AssetMovementModel::where('asset_id', $asset['id'])->count());
    }

    public function test_hoja_de_vida_pdf_y_etiquetas(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->get("/api/v1/assets/{$asset['id']}/life-sheet")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->withHeaders($this->auth())->get("/api/v1/assets/labels?ids={$asset['id']}")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sin_permiso_no_ve_activos(): void
    {
        $user = $this->userWith(['tablero.ver']);

        $this->withHeaders($this->auth($user))->getJson('/api/v1/assets')->assertForbidden();
    }
}
