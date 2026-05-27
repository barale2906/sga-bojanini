<?php

declare(strict_types=1);

namespace Tests\Unit\Warehouse;

use App\Modules\Warehouse\Domain\Entities\Location;
use PHPUnit\Framework\TestCase;

class LocationEntityTest extends TestCase
{
    private function makeLocation(?float $volumeCm3, ?float $maxWeightKg): Location
    {
        return new Location(
            id:          1,
            zoneId:      1,
            name:        'Estante A-01',
            code:        'EST-A-01',
            volumeCm3:   $volumeCm3,
            maxWeightKg: $maxWeightKg,
        );
    }

    // ── hasVolumeFor ──────────────────────────────────────────────────────────

    public function test_has_volume_for_sin_limite_siempre_retorna_true(): void
    {
        $loc = $this->makeLocation(null, null);

        $this->assertTrue($loc->hasVolumeFor(999999.0));
        $this->assertTrue($loc->hasVolumeFor(0.0));
    }

    public function test_has_volume_for_dentro_del_limite_retorna_true(): void
    {
        $loc = $this->makeLocation(50000.0, null);

        $this->assertTrue($loc->hasVolumeFor(20000.0, 10000.0));  // 10000 + 20000 = 30000 ≤ 50000
        $this->assertTrue($loc->hasVolumeFor(50000.0, 0.0));       // exacto al límite
    }

    public function test_has_volume_for_excede_limite_retorna_false(): void
    {
        $loc = $this->makeLocation(50000.0, null);

        $this->assertFalse($loc->hasVolumeFor(40000.0, 20000.0)); // 20000 + 40000 = 60000 > 50000
        $this->assertFalse($loc->hasVolumeFor(50001.0, 0.0));
    }

    public function test_has_volume_for_exactamente_en_el_limite_es_true(): void
    {
        $loc = $this->makeLocation(50000.0, null);

        $this->assertTrue($loc->hasVolumeFor(25000.0, 25000.0)); // 25000 + 25000 = 50000
    }

    // ── hasWeightCapacityFor ──────────────────────────────────────────────────

    public function test_has_weight_capacity_sin_limite_siempre_retorna_true(): void
    {
        $loc = $this->makeLocation(null, null);

        $this->assertTrue($loc->hasWeightCapacityFor(99999.0));
        $this->assertTrue($loc->hasWeightCapacityFor(0.0));
    }

    public function test_has_weight_capacity_dentro_del_limite_retorna_true(): void
    {
        $loc = $this->makeLocation(null, 200.0);

        $this->assertTrue($loc->hasWeightCapacityFor(80.0, 50.0));  // 50 + 80 = 130 ≤ 200
        $this->assertTrue($loc->hasWeightCapacityFor(200.0, 0.0));  // exacto
    }

    public function test_has_weight_capacity_excede_limite_retorna_false(): void
    {
        $loc = $this->makeLocation(null, 200.0);

        $this->assertFalse($loc->hasWeightCapacityFor(150.0, 100.0)); // 100 + 150 = 250 > 200
        $this->assertFalse($loc->hasWeightCapacityFor(200.01, 0.0));
    }

    public function test_has_weight_capacity_exactamente_en_el_limite_es_true(): void
    {
        $loc = $this->makeLocation(null, 200.0);

        $this->assertTrue($loc->hasWeightCapacityFor(100.0, 100.0)); // 100 + 100 = 200
    }

    // ── Getters básicos ───────────────────────────────────────────────────────

    public function test_getters_retornan_valores_correctos(): void
    {
        $loc = $this->makeLocation(50000.0, 200.0);

        $this->assertSame(50000.0, $loc->getVolumeCm3());
        $this->assertSame(200.0,   $loc->getMaxWeightKg());
        $this->assertSame('EST-A-01', $loc->getCode());
        $this->assertTrue($loc->isActive());
    }

    public function test_getters_retornan_null_cuando_no_configurados(): void
    {
        $loc = $this->makeLocation(null, null);

        $this->assertNull($loc->getVolumeCm3());
        $this->assertNull($loc->getMaxWeightKg());
    }
}
