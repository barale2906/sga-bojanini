<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\ProcedurePrice;
use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;

class GetCurrentPriceForProcedureUseCase
{
    public function __construct(
        private readonly ProcedurePriceRepositoryInterface $repo,
    ) {}

    public function execute(int $medicalServiceId): ?ProcedurePrice
    {
        return $this->repo->findCurrentPrice($medicalServiceId);
    }
}
