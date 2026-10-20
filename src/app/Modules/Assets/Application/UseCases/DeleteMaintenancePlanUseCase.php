<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\Services\MaintenancePlanScheduler;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use Illuminate\Support\Facades\DB;

/** Elimina un plan (soft delete) y cancela su orden programada pendiente. */
class DeleteMaintenancePlanUseCase
{
    public function __construct(
        private readonly MaintenancePlanScheduler $scheduler,
    ) {}

    public function execute(int $planId, int $userId): void
    {
        DB::transaction(function () use ($planId, $userId) {
            $plan = AssetMaintenancePlanModel::findOrFail($planId);
            $plan->delete();
            $this->scheduler->sync($plan, $userId);
        });
    }
}
