<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalStepModel;
use Illuminate\Support\Facades\DB;

class CreateApprovalFlowUseCase
{
    public function execute(array $data): ApprovalFlowModel
    {
        return DB::transaction(function () use ($data) {
            $flow = ApprovalFlowModel::create([
                'name'        => $data['name'],
                'entity_type' => $data['entity_type'],
                'conditions'  => $data['conditions'] ?? null,
                'is_active'   => $data['is_active'] ?? true,
            ]);

            $this->syncSteps($flow, $data['steps'] ?? []);

            return $flow->fresh('steps.role');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function syncSteps(ApprovalFlowModel $flow, array $steps): void
    {
        foreach ($steps as $step) {
            ApprovalStepModel::create([
                'approval_flow_id' => $flow->id,
                'step_order'       => $step['step_order'],
                'role_id'          => $step['role_id'],
                'is_required'      => $step['is_required'] ?? true,
            ]);
        }
    }
}
