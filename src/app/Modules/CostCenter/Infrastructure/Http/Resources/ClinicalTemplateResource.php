<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\ClinicalTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $t = $this->resource;

        if ($t instanceof ClinicalTemplate) {
            return [
                'id'                   => $t->getId(),
                'medical_service_id'   => $t->getMedicalServiceId(),
                'medical_service_name' => $t->getMedicalServiceName(),
                'title'                => $t->getTitle(),
                'content'              => $t->getContent(),
                'is_active'            => $t->isActive(),
            ];
        }

        return [
            'id'                   => $t->id,
            'medical_service_id'   => $t->medical_service_id,
            'medical_service_name' => $t->medicalService?->name,
            'title'                => $t->title,
            'content'              => $t->content,
            'is_active'            => $t->is_active,
        ];
    }
}
