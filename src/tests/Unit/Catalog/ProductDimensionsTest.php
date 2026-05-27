<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Enums\ProductType;
use PHPUnit\Framework\TestCase;

class ProductDimensionsTest extends TestCase
{
    private function makeProduct(?float $volumeCm3, ?float $weightKg): Product
    {
        return new Product(
            id:           1,
            categoryId:   1,
            baseUnitId:   1,
            productType:  ProductType::Simple,
            name:         'Producto Test',
            code:         'PRD-TEST',
            volumeCm3:    $volumeCm3,
            weightKg:     $weightKg,
        );
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function test_getters_retornan_dimensiones_asignadas(): void
    {
        $product = $this->makeProduct(1200.50, 0.85);

        $this->assertSame(1200.50, $product->getVolumeCm3());
        $this->assertSame(0.85,    $product->getWeightKg());
    }

    public function test_getters_retornan_null_cuando_no_configurados(): void
    {
        $product = $this->makeProduct(null, null);

        $this->assertNull($product->getVolumeCm3());
        $this->assertNull($product->getWeightKg());
    }

    // ── totalVolume ───────────────────────────────────────────────────────────

    public function test_total_volume_sin_dimensiones_retorna_null(): void
    {
        $product = $this->makeProduct(null, null);

        $this->assertNull($product->totalVolume(10));
    }

    public function test_total_volume_calcula_multiplicando_por_cantidad(): void
    {
        $product = $this->makeProduct(1200.0, null);

        $this->assertEqualsWithDelta(6000.0, $product->totalVolume(5), 0.001);
        $this->assertEqualsWithDelta(1200.0, $product->totalVolume(1), 0.001);
        $this->assertEqualsWithDelta(0.0,    $product->totalVolume(0), 0.001);
    }

    public function test_total_volume_con_decimales(): void
    {
        $product = $this->makeProduct(333.33, null);

        $this->assertEqualsWithDelta(999.99, $product->totalVolume(3), 0.001);
    }

    // ── totalWeight ───────────────────────────────────────────────────────────

    public function test_total_weight_sin_dimensiones_retorna_null(): void
    {
        $product = $this->makeProduct(null, null);

        $this->assertNull($product->totalWeight(10));
    }

    public function test_total_weight_calcula_multiplicando_por_cantidad(): void
    {
        $product = $this->makeProduct(null, 0.85);

        $this->assertEqualsWithDelta(4.25, $product->totalWeight(5),  0.001);
        $this->assertEqualsWithDelta(0.85, $product->totalWeight(1),  0.001);
        $this->assertEqualsWithDelta(0.0,  $product->totalWeight(0),  0.001);
    }

    public function test_total_weight_con_decimales(): void
    {
        $product = $this->makeProduct(null, 1.5);

        $this->assertEqualsWithDelta(15.0, $product->totalWeight(10), 0.001);
    }

    // ── Ambos definidos ───────────────────────────────────────────────────────

    public function test_total_volume_y_weight_ambos_definidos(): void
    {
        $product = $this->makeProduct(500.0, 2.0);

        $this->assertEqualsWithDelta(2500.0, $product->totalVolume(5), 0.001);
        $this->assertEqualsWithDelta(10.0,   $product->totalWeight(5), 0.001);
    }
}
