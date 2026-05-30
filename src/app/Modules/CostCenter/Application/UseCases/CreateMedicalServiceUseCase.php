<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\MedicalServiceData;
use App\Modules\CostCenter\Domain\Entities\MedicalService;
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

        $service = new MedicalService(
            id:          null,
            code:        strtoupper(trim($data->code)),
            name:        $data->name,
            description: $data->description,
            isActive:    $data->isActive,
        );

        return $this->repository->save($service);
    }
}
