<?php

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Entities\ProductKitComponent;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Services\KitExplosionService;
use App\Modules\Catalog\Domain\Services\KitRecipeValidator;
use Mockery;
use Tests\TestCase;

class KitExplosionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_explode_calcula_cantidades_base(): void
    {
        $kit = new Product(
            id: 10,
            categoryId: 1,
            baseUnitId: 1,
            productType: ProductType::Kit,
            name: 'Kit',
            code: 'KIT-1',
        );

        $component = new Product(
            id: 20,
            categoryId: 1,
            baseUnitId: 1,
            productType: ProductType::Simple,
            name: 'Gasa',
            code: 'GAS-1',
        );

        $productRepo = Mockery::mock(ProductRepositoryInterface::class);
        $productRepo->shouldReceive('findById')->with(10)->andReturn($kit);
        $productRepo->shouldReceive('findById')->with(20)->andReturn($component);

        $kitRepo = Mockery::mock(ProductKitComponentRepositoryInterface::class);
        $kitRepo->shouldReceive('findByKitProductId')->with(10)->andReturn([
            new ProductKitComponent(null, 10, 20, 5, 0),
        ]);

        $validator = Mockery::mock(KitRecipeValidator::class);
        $service = new KitExplosionService($productRepo, $kitRepo, $validator);

        $lines = $service->explode(10, 2);

        $this->assertCount(1, $lines);
        $this->assertSame(10, $lines[0]['quantity_base']);
    }
}
