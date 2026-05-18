<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use Illuminate\Support\Collection;

class ListApprovalFlowsUseCase
{
    public function execute(?string $entityType = null): Collection
    {
        $query = ApprovalFlowModel::with('steps.role')->orderBy('name');

        if ($entityType !== null) {
            $query->where('entity_type', $entityType);
        }

        return $query->get();
    }
}
