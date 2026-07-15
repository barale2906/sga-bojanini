<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;

class ListProductSanitaryRegistrationsUseCase
{
    public function __construct(
        private readonly ProductSanitaryRegistrationRepositoryInterface $repository,
    ) {}

    /**
     * @param bool $onlyActive Si true, retorna solo los registros activos y no vencidos.
     * @return \App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration[]
     */
    public function execute(int $productVariantId, bool $onlyActive = false): array
    {
        return $this->repository->findByProductVariantId($productVariantId, $onlyActive);
    }
}
