<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;

/**
 * Elimina (soft delete) un activo registrado por error. Si ya tiene
 * historia (mantenimientos realizados o traslados confirmados) se debe dar de baja.
 */
class DeleteAssetUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function execute(int $id): void
    {
        if ($this->assets->findById($id) === null) {
            throw new \DomainException("Activo con id {$id} no encontrado.");
        }

        if ($this->assets->hasHistory($id)) {
            throw new \DomainException('El activo tiene mantenimientos o traslados registrados; regístrelo como baja en lugar de eliminarlo.');
        }

        $this->assets->delete($id);
    }
}
