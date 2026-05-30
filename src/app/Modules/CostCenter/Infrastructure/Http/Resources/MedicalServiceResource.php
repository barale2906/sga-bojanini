<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\MedicalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $svc = $this->resource;

        if ($svc instanceof MedicalService) {
            return [
                'id'          => $svc->getId(),
                'code'        => $svc->getCode(),
                'name'        => $svc->getName(),
                'description' => $svc->getDescription(),
                'is_active'   => $svc->isActive(),
            ];
        }

        return [
            'id'          => $svc->id,
            'code'        => $svc->code,
            'name'        => $svc->name,
            'description' => $svc->description,
            'is_active'   => $svc->is_active,
        ];
    }
}
