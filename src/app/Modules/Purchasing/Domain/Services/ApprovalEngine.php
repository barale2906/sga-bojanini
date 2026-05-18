<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Domain\Services;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalRecordModel;

class ApprovalEngine
{
    public const ENTITY_PURCHASE_ORDER = 'purchase_order';

    /**
     * @return array<int, ApprovalRecordModel>
     */
    public function initiateApproval(string $entityType, int $entityId, array $context = []): array
    {
        $flow = $this->findApplicableFlow($entityType, $context);

        if ($flow === null) {
            throw new \DomainException('No se encontró un flujo de aprobación aplicable.');
        }

        $records = [];

        foreach ($flow->steps()->orderBy('step_order')->get() as $step) {
            $records[] = ApprovalRecordModel::create([
                'approvable_type'  => $entityType,
                'approvable_id'    => $entityId,
                'approval_step_id' => $step->id,
                'user_id'          => null,
                'status'           => 'pending',
            ]);
        }

        return $records;
    }

    public function processDecision(int $recordId, int $userId, string $decision, ?string $comments = null): ApprovalRecordModel
    {
        $record = ApprovalRecordModel::with('step.role')->findOrFail($recordId);

        if ($record->status !== 'pending') {
            throw new \DomainException('Este paso ya fue procesado.');
        }

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new \DomainException('La decisión debe ser approved o rejected.');
        }

        $requiredRole = $record->step->role->name;
        $user = UserModel::findOrFail($userId);

        if (! $user->hasRole($requiredRole)) {
            throw new \DomainException("Se requiere el rol '{$requiredRole}' para aprobar este paso.");
        }

        $record->user_id = $userId;
        $record->status = $decision;
        $record->comments = $comments;
        $record->decided_at = now();
        $record->save();

        return $record;
    }

    public function findPendingRecordForUser(string $entityType, int $entityId, int $userId): ?ApprovalRecordModel
    {
        $user = UserModel::findOrFail($userId);
        $roleNames = $user->getRoleNames()->all();

        if ($roleNames === []) {
            return null;
        }

        return ApprovalRecordModel::with('step.role')
            ->where('approvable_type', $entityType)
            ->where('approvable_id', $entityId)
            ->where('status', 'pending')
            ->whereHas('step.role', fn ($q) => $q->whereIn('name', $roleNames))
            ->orderBy('id')
            ->first();
    }

    public function isFullyApproved(string $entityType, int $entityId): bool
    {
        $pendingRequired = ApprovalRecordModel::where('approvable_type', $entityType)
            ->where('approvable_id', $entityId)
            ->whereHas('step', fn ($q) => $q->where('is_required', true))
            ->where('status', '!=', 'approved')
            ->count();

        return $pendingRequired === 0;
    }

    public function isRejected(string $entityType, int $entityId): bool
    {
        return ApprovalRecordModel::where('approvable_type', $entityType)
            ->where('approvable_id', $entityId)
            ->where('status', 'rejected')
            ->exists();
    }

    private function findApplicableFlow(string $entityType, array $context): ?ApprovalFlowModel
    {
        $flows = ApprovalFlowModel::where('entity_type', $entityType)
            ->where('is_active', true)
            ->with('steps')
            ->get();

        foreach ($flows as $flow) {
            $conditions = $flow->conditions ?? [];

            if ($conditions === []) {
                return $flow;
            }

            $matches = true;

            if (isset($conditions['min_amount'], $context['total_amount'])) {
                if ($context['total_amount'] < $conditions['min_amount']) {
                    $matches = false;
                }
            }

            if (isset($conditions['max_amount'], $context['total_amount'])) {
                if ($context['total_amount'] > $conditions['max_amount']) {
                    $matches = false;
                }
            }

            if ($matches) {
                return $flow;
            }
        }

        return $flows->first();
    }
}
