<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalStepModel;
use Illuminate\Support\Facades\DB;

class UpdateApprovalFlowUseCase
{
    public function execute(int $flowId, array $data): ApprovalFlowModel
    {
        return DB::transaction(function () use ($flowId, $data) {
            $flow = ApprovalFlowModel::findOrFail($flowId);

            $flow->update([
                'name'        => $data['name'],
                'entity_type' => $data['entity_type'],
                'conditions'  => $data['conditions'] ?? null,
                'is_active'   => $data['is_active'] ?? $flow->is_active,
            ]);

            if (isset($data['steps'])) {
                $flow->steps()->delete();

                foreach ($data['steps'] as $step) {
                    ApprovalStepModel::create([
                        'approval_flow_id' => $flow->id,
                        'step_order'       => $step['step_order'],
                        'role_id'          => $step['role_id'],
                        'is_required'      => $step['is_required'] ?? true,
                    ]);
                }
            }

            return $flow->fresh('steps.role');
        });
    }
}
