<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\MedicalServiceData;
use App\Modules\CostCenter\Domain\Entities\MedicalService;
use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

class CreateMedicalServiceUseCase
{
    public function __construct(
        private readonly MedicalServiceRepositoryInterface $repository,
    ) {}

    public function execute(MedicalServiceData $data): MedicalService
    {
        if ($this->repository->findByCode(strtoupper(trim($data->code))) !== null) {
            throw new \DomainException("Ya existe un servicio médico con el código '{$data->code}'.");
        }

        if ($data->type === MedicalServiceType::PROCEDURE) {
            $this->validateParent($data->parentId);
        }

        if ($data->type === MedicalServiceType::SERVICE && $data->parentId !== null) {
            throw new \DomainException('Un servicio no puede tener un padre. Solo los procedimientos tienen padre.');
        }

        $service = new MedicalService(
            id:           null,
            code:         strtoupper(trim($data->code)),
            name:         $data->name,
            description:  $data->description,
            type:         $data->type,
            parentId:     $data->parentId,
            isActive:     $data->isActive,
            externalCode: $data->externalCode,
        );

        return $this->repository->save($service);
    }

    private function validateParent(?int $parentId): void
    {
        if ($parentId === null) {
            throw new \DomainException('Un procedimiento debe tener un servicio padre (parent_id es requerido).');
        }

        $parent = $this->repository->findById($parentId);

        if ($parent === null) {
            throw new \DomainException("El servicio padre con id {$parentId} no existe.");
        }

        if ($parent->isProcedure()) {
            throw new \DomainException('Un procedimiento no puede ser hijo de otro procedimiento. Solo puede ser hijo de un servicio.');
        }
    }
}
