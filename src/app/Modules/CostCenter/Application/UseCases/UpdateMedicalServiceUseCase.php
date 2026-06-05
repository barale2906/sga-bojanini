<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\MedicalServiceData;
use App\Modules\CostCenter\Domain\Entities\MedicalService;
use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

class UpdateMedicalServiceUseCase
{
    public function __construct(
        private readonly MedicalServiceRepositoryInterface $repository,
    ) {}

    public function execute(int $id, MedicalServiceData $data): MedicalService
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException("Servicio médico con id {$id} no encontrado.");
        }

        $codeConflict = $this->repository->findByCode(strtoupper(trim($data->code)));

        if ($codeConflict !== null && $codeConflict->getId() !== $id) {
            throw new \DomainException("Ya existe un servicio médico con el código '{$data->code}'.");
        }

        // No permite cambiar el tipo una vez creado para preservar integridad del árbol
        if ($existing->getType() !== $data->type) {
            throw new \DomainException(
                "No se puede cambiar el tipo de '{$existing->getType()->label()}' a '{$data->type->label()}' una vez creado."
            );
        }

        if ($data->type === MedicalServiceType::PROCEDURE) {
            $this->validateParent($data->parentId, $id);
        }

        $updated = new MedicalService(
            id:          $id,
            code:        strtoupper(trim($data->code)),
            name:        $data->name,
            description: $data->description,
            type:        $data->type,
            parentId:    $data->parentId,
            isActive:    $data->isActive,
        );

        return $this->repository->save($updated);
    }

    private function validateParent(?int $parentId, int $selfId): void
    {
        if ($parentId === null) {
            throw new \DomainException('Un procedimiento debe tener un servicio padre (parent_id es requerido).');
        }

        if ($parentId === $selfId) {
            throw new \DomainException('Un procedimiento no puede ser su propio padre.');
        }

        $parent = $this->repository->findById($parentId);

        if ($parent === null) {
            throw new \DomainException("El servicio padre con id {$parentId} no existe.");
        }

        if ($parent->isProcedure()) {
            throw new \DomainException('Un procedimiento no puede ser hijo de otro procedimiento.');
        }
    }
}
