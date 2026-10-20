<?php

namespace Tests\Unit\Assets;

use App\Modules\Assets\Domain\Entities\Asset;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Services\AssetTransferPolicy;
use PHPUnit\Framework\TestCase;

class AssetTransferPolicyTest extends TestCase
{
    private AssetTransferPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new AssetTransferPolicy();
    }

    private function asset(array $overrides = []): Asset
    {
        $args = array_merge([
            'id' => 1, 'code' => '000001', 'name' => 'Monitor', 'categoryId' => 1, 'assetType' => AssetType::Biomedical,
            'status' => AssetStatus::Operational, 'currentPlaceId' => 10, 'isOffPremises' => false, 'responsibleId' => 5,
        ], $overrides);

        return new Asset(...$args);
    }

    public function test_reubicacion_en_la_misma_sede_es_interna(): void
    {
        $type = $this->policy->resolveType($this->asset(), AssetTransferType::Relocation, false, targetPlaceId: 11, currentSiteId: 1, targetSiteId: 1);

        $this->assertSame(AssetTransferType::Relocation, $type);
        $this->assertFalse($type->requiresSignature());
    }

    public function test_reubicacion_a_otra_sede_requiere_firma(): void
    {
        $type = $this->policy->resolveType($this->asset(), AssetTransferType::Relocation, false, targetPlaceId: 20, currentSiteId: 1, targetSiteId: 2);

        $this->assertSame(AssetTransferType::SiteTransfer, $type);
        $this->assertTrue($type->requiresSignature());
    }

    public function test_solo_cambio_de_responsable_es_valido(): void
    {
        $type = $this->policy->resolveType($this->asset(), AssetTransferType::Relocation, false, targetPlaceId: 10, currentSiteId: 1, targetResponsibleId: 9);

        $this->assertSame(AssetTransferType::Relocation, $type);
    }

    public function test_sin_cambios_es_rechazado(): void
    {
        $this->expectException(\DomainException::class);
        $this->policy->resolveType($this->asset(), AssetTransferType::Relocation, false, targetPlaceId: 10, currentSiteId: 1, targetSiteId: 1, targetResponsibleId: 5);
    }

    public function test_salidas_externas_requieren_firma_y_retorno_no(): void
    {
        foreach ([AssetTransferType::ExternalRepair, AssetTransferType::Loan, AssetTransferType::Retirement] as $type) {
            $this->assertTrue($type->requiresSignature(), $type->value);
            $this->assertTrue($type->leavesPremises(), $type->value);
        }

        $this->assertFalse(AssetTransferType::Return->requiresSignature());
    }

    public function test_traslado_pendiente_bloquea_otro(): void
    {
        $this->expectException(\DomainException::class);
        $this->policy->resolveType($this->asset(), AssetTransferType::Loan, true);
    }

    public function test_retorno_solo_si_esta_fuera(): void
    {
        $this->assertSame(AssetTransferType::Return, $this->policy->resolveType($this->asset(['isOffPremises' => true, 'currentPlaceId' => null]), AssetTransferType::Return, false));

        $this->expectException(\DomainException::class);
        $this->policy->resolveType($this->asset(), AssetTransferType::Return, false);
    }

    public function test_fuera_de_las_instalaciones_solo_admite_retorno_o_baja(): void
    {
        $outside = $this->asset(['isOffPremises' => true, 'currentPlaceId' => null]);

        $this->assertSame(AssetTransferType::Retirement, $this->policy->resolveType($outside, AssetTransferType::Retirement, false));

        $this->expectException(\DomainException::class);
        $this->policy->resolveType($outside, AssetTransferType::Loan, false);
    }

    public function test_activo_de_baja_no_se_traslada(): void
    {
        $this->expectException(\DomainException::class);
        $this->policy->resolveType($this->asset(['status' => AssetStatus::Retired]), AssetTransferType::Relocation, false, targetPlaceId: 11);
    }

    public function test_site_transfer_no_se_solicita_directamente(): void
    {
        $this->expectException(\DomainException::class);
        $this->policy->resolveType($this->asset(), AssetTransferType::SiteTransfer, false);
    }
}
