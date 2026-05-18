<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Http\Resources;

use App\Modules\Audit\Domain\Entities\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        return [
            'id'              => $log->getId(),
            'user_id'         => $log->getUserId(),
            'user_name'       => $log->getUserName(),
            'action'          => $log->getAction(),
            'auditable_type'  => $log->getAuditableType(),
            'auditable_id'    => $log->getAuditableId(),
            'old_values'      => $log->getOldValues(),
            'new_values'      => $log->getNewValues(),
            'ip_address'      => $log->getIpAddress(),
            'user_agent'      => $log->getUserAgent(),
            'created_at'      => $log->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
