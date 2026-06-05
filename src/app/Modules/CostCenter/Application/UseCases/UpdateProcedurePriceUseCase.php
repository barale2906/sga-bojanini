<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\ProcedurePriceData;
use App\Modules\CostCenter\Domain\Entities\ProcedurePrice;
use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;

class UpdateProcedurePriceUseCase
{
    public function __construct(
        private readonly ProcedurePriceRepositoryInterface $repository,
    ) {}

    public function execute(int $id, ProcedurePriceData $data): ProcedurePrice
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Tarifa con id {$id} no encontrada.");
        }

        if ($data->effectiveTo !== null && $data->effectiveTo < $data->effectiveFrom) {
            throw new \DomainException('La fecha de vencimiento no puede ser anterior a la fecha de vigencia.');
        }

        $updated = new ProcedurePrice(
            id:               $id,
            medicalServiceId: $data->medicalServiceId,
            unitPrice:        $data->unitPrice,
            effectiveFrom:    $data->effectiveFrom,
            effectiveTo:      $data->effectiveTo,
            isActive:         $data->isActive,
            notes:            $data->notes,
        );

        return $this->repository->save($updated);
    }
}
