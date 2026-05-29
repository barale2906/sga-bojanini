<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;

class DeleteProductSanitaryRegistrationUseCase
{
    public function __construct(
        private readonly ProductSanitaryRegistrationRepositoryInterface $repository,
    ) {}

    /**
     * @throws \DomainException Si el registro no existe.
     */
    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Registro sanitario no encontrado.');
        }

        $this->repository->delete($id);
    }
}
