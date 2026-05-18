<?php

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use Mockery;
use Tests\TestCase;

class PresentationConverterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_to_base_multiplica_factor(): void
    {
        $repo = Mockery::mock(ProductPresentationRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(1)->andReturn(new ProductPresentation(
            id: 1,
            productId: 1,
            parentId: null,
            name: 'Paquete',
            code: 'PQ-100',
            unitsOfMeasureId: 1,
            quantityPerParent: null,
            factorToBase: 100,
            level: 1,
        ));

        $converter = new PresentationConverter($repo);

        $this->assertSame(200, $converter->toBase(1, 2));
    }

    public function test_from_base_requiere_division_exacta(): void
    {
        $repo = Mockery::mock(ProductPresentationRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(1)->andReturn(new ProductPresentation(
            id: 1,
            productId: 1,
            parentId: null,
            name: 'Paquete',
            code: 'PQ-100',
            unitsOfMeasureId: 1,
            quantityPerParent: null,
            factorToBase: 100,
            level: 1,
        ));

        $converter = new PresentationConverter($repo);

        $this->assertSame(2, $converter->fromBase(1, 200));
    }

    public function test_from_base_falla_si_no_divide_exacto(): void
    {
        $this->expectException(\DomainException::class);

        $repo = Mockery::mock(ProductPresentationRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(1)->andReturn(new ProductPresentation(
            id: 1,
            productId: 1,
            parentId: null,
            name: 'Paquete',
            code: 'PQ-100',
            unitsOfMeasureId: 1,
            quantityPerParent: null,
            factorToBase: 100,
            level: 1,
        ));

        (new PresentationConverter($repo))->fromBase(1, 150);
    }
}
