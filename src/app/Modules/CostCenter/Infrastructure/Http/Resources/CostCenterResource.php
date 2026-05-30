<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\CostCenter;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostCenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cc = $this->resource;

        if ($cc instanceof CostCenter) {
            return [
                'id'          => $cc->getId(),
                'code'        => $cc->getCode(),
                'name'        => $cc->getName(),
                'type'        => $cc->getType()->value,
                'type_label'  => $cc->getType()->label(),
                'is_external' => $cc->isExternal(),
                'description' => $cc->getDescription(),
                'is_active'   => $cc->isActive(),
            ];
        }

        /** @var CostCenterModel $cc */
        return [
            'id'          => $cc->id,
            'code'        => $cc->code,
            'name'        => $cc->name,
            'type'        => $cc->type,
            'type_label'  => $cc->type === 'external' ? 'Externo (Pacientes)' : 'Interno',
            'is_external' => $cc->type === 'external',
            'description' => $cc->description,
            'is_active'   => $cc->is_active,
        ];
    }
}
