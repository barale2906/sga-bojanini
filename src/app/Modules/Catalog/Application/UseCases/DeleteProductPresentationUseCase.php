<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;

class DeleteProductPresentationUseCase
{
    public function __construct(
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->presentationRepository->findById($id) === null) {
            throw new \DomainException('Presentación no encontrada.');
        }

        $this->presentationRepository->delete($id);
    }
}
