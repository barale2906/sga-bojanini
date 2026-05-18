<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;

class PresentationConverter
{
    public function __construct(
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function toBase(int $presentationId, int $quantity): int
    {
        $presentation = $this->presentationRepository->findById($presentationId);

        if ($presentation === null) {
            throw new \DomainException("Presentación {$presentationId} no encontrada.");
        }

        if ($quantity < 1) {
            throw new \DomainException('La cantidad debe ser mayor a cero.');
        }

        return $quantity * $presentation->getFactorToBase();
    }

    public function fromBase(int $presentationId, int $quantityBase): int
    {
        $presentation = $this->presentationRepository->findById($presentationId);

        if ($presentation === null) {
            throw new \DomainException("Presentación {$presentationId} no encontrada.");
        }

        $factor = $presentation->getFactorToBase();

        if ($quantityBase % $factor !== 0) {
            throw new \DomainException(
                "No se puede convertir {$quantityBase} unidades base a presentación con factor {$factor}."
            );
        }

        return (int) ($quantityBase / $factor);
    }
}
