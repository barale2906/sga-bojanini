<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\MedicalServiceData;
use App\Modules\CostCenter\Domain\Entities\MedicalService;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

class UpdateMedicalServiceUseCase
{
    public function __construct(
        private readonly MedicalServiceRepositoryInterface $repository,
    ) {}

    public function execute(int $id, MedicalServiceData $data): MedicalService
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Servicio médico con id {$id} no encontrado.");
        }

        $codeConflict = $this->repository->findByCode(strtoupper(trim($data->code)));

        if ($codeConflict !== null && $codeConflict->getId() !== $id) {
            throw new \DomainException("Ya existe un servicio médico con el código '{$data->code}'.");
        }

        $updated = new MedicalService(
            id:          $id,
            code:        strtoupper(trim($data->code)),
            name:        $data->name,
            description: $data->description,
            isActive:    $data->isActive,
        );

        return $this->repository->save($updated);
    }
}
