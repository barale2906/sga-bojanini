<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\ProcedurePriceData;
use App\Modules\CostCenter\Domain\Entities\ProcedurePrice;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;

class CreateProcedurePriceUseCase
{
    public function __construct(
        private readonly ProcedurePriceRepositoryInterface $repository,
        private readonly MedicalServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(ProcedurePriceData $data): ProcedurePrice
    {
        $procedure = $this->serviceRepository->findById($data->medicalServiceId);

        if ($procedure === null) {
            throw new \DomainException("Procedimiento con id {$data->medicalServiceId} no encontrado.");
        }

        if (! $procedure->isProcedure()) {
            throw new \DomainException("El registro '{$procedure->getName()}' es un servicio, no un procedimiento. Las tarifas solo aplican a procedimientos.");
        }

        if ($data->effectiveTo !== null && $data->effectiveTo < $data->effectiveFrom) {
            throw new \DomainException('La fecha de vencimiento no puede ser anterior a la fecha de vigencia.');
        }

        $price = new ProcedurePrice(
            id:               null,
            medicalServiceId: $data->medicalServiceId,
            unitPrice:        $data->unitPrice,
            effectiveFrom:    $data->effectiveFrom,
            effectiveTo:      $data->effectiveTo,
            isActive:         $data->isActive,
            notes:            $data->notes,
        );

        return $this->repository->save($price);
    }
}
