<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSiteModel;

/** Categorías de activos y ubicaciones Sede → Sitio. */
class AssetCatalogTest extends AssetTestCase
{
    public function test_lista_categorias_base_con_tipo_y_si_requieren_mantenimiento(): void
    {
        $data = $this->withHeaders($this->auth())->getJson('/api/v1/assets/categories')
            ->assertOk()
            ->json('data');

        $bio = collect($data)->firstWhere('code', 'BIO');
        $mue = collect($data)->firstWhere('code', 'MUE');

        $this->assertSame('biomedical', $bio['asset_type']);
        $this->assertTrue($bio['requires_maintenance']);
        $this->assertFalse($mue['requires_maintenance']);
    }

    public function test_crea_y_actualiza_categoria_con_codigo_en_mayusculas(): void
    {
        $id = $this->withHeaders($this->auth())->postJson('/api/v1/assets/categories', [
            'code' => 'lab', 'name' => 'Equipos de laboratorio', 'asset_type' => 'biomedical',
        ])->assertCreated()->assertJsonPath('data.code', 'LAB')->json('data.id');

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/categories/{$id}", [
            'code' => 'LAB', 'name' => 'Laboratorio', 'asset_type' => 'biomedical', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_codigo_de_categoria_duplicado_responde_409(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/v1/assets/categories', [
            'code' => 'BIO', 'name' => 'Duplicada', 'asset_type' => 'general',
        ])->assertStatus(409);
    }

    public function test_no_elimina_categoria_con_activos(): void
    {
        $this->createAsset('BIO');

        $this->withHeaders($this->auth())->deleteJson('/api/v1/assets/categories/' . $this->category('BIO')->id)
            ->assertStatus(409);
    }

    public function test_no_cambia_a_tipo_general_si_hay_hojas_de_vida(): void
    {
        $asset = $this->createAsset('BIO');
        $this->withHeaders($this->auth())->putJson("/api/v1/assets/{$asset['id']}/technical-profile", ['manufacturer' => 'Mindray'])->assertOk();

        $this->withHeaders($this->auth())->putJson('/api/v1/assets/categories/' . $this->category('BIO')->id, [
            'code' => 'BIO', 'name' => 'Equipo biomédico', 'asset_type' => 'general',
        ])->assertStatus(409);
    }

    public function test_crea_sede_y_sitio_y_los_lista_anidados(): void
    {
        $siteId = $this->withHeaders($this->auth())->postJson('/api/v1/assets/sites', ['name' => 'Edificio 2'])
            ->assertCreated()->json('data.id');

        $this->withHeaders($this->auth())->postJson('/api/v1/assets/places', ['site_id' => $siteId, 'name' => 'Call center'])
            ->assertCreated()->assertJsonPath('data.name', 'Call center');

        $sites = $this->withHeaders($this->auth())->getJson('/api/v1/assets/sites')->assertOk()->json('data');
        $site  = collect($sites)->firstWhere('id', $siteId);

        $this->assertSame('Call center', $site['places'][0]['name']);
        $this->assertSame(0, $site['places'][0]['assets_count']);
    }

    public function test_sitio_duplicado_en_la_misma_sede_responde_409(): void
    {
        $place = $this->place('Clínica', 'Consultorio 1');

        $this->withHeaders($this->auth())->postJson('/api/v1/assets/places', ['site_id' => $place->site_id, 'name' => 'Consultorio 1'])
            ->assertStatus(409);
    }

    public function test_no_elimina_sitio_con_activos_ni_sede_con_sitios(): void
    {
        $this->createAsset();
        $place = $this->place();

        $this->withHeaders($this->auth())->deleteJson("/api/v1/assets/places/{$place->id}")->assertStatus(409);
        $this->withHeaders($this->auth())->deleteJson("/api/v1/assets/sites/{$place->site_id}")->assertStatus(409);
    }

    public function test_elimina_sitio_sin_uso(): void
    {
        $place = $this->place('Clínica', 'Bodega');

        $this->withHeaders($this->auth())->deleteJson("/api/v1/assets/places/{$place->id}")->assertOk();
        $this->assertSoftDeleted(AssetPlaceModel::class, ['id' => $place->id]);
        $this->assertNotNull(AssetSiteModel::find($place->site_id));
    }

    public function test_usuario_solo_con_ver_no_puede_crear_catalogos(): void
    {
        $viewer = $this->userWith(['activos.ver']);

        $this->withHeaders($this->auth($viewer))->getJson('/api/v1/assets/sites')->assertOk();
        $this->withHeaders($this->auth($viewer))->postJson('/api/v1/assets/sites', ['name' => 'Sede X'])->assertForbidden();
    }
}
