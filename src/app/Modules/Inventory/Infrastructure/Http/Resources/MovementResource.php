<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $movement = $this->resource;

        if (method_exists($movement, 'getId')) {
            return [
                'id'                  => $movement->getId(),
                'warehouse_id'        => $movement->getWarehouseId(),
                'product_id'          => $movement->getProductId(),
                'batch_id'            => $movement->getBatchId(),
                'location_from_id'    => $movement->getLocationFromId(),
                'location_to_id'      => $movement->getLocationToId(),
                'movement_type'       => $movement->getMovementType(),
                'quantity'            => $movement->getQuantity(),
                'reason'              => $movement->getReason(),
                'reference_type'      => $movement->getReferenceType(),
                'reference_id'        => $movement->getReferenceId(),
                'cost_center_id'      => $movement->getCostCenterId(),
                'service_id'          => $movement->getServiceId(),
                'patient_document'    => $movement->getPatientDocument(),
                'patient_external_id' => $movement->getPatientExternalId(),
                'user_id'             => $movement->getUserId(),
                'created_at'          => $movement->getCreatedAt(),
            ];
        }

        return [
            'id'                  => $movement->id,
            'warehouse_id'        => $movement->warehouse_id,
            'warehouse_to_id'     => $movement->warehouse_to_id,
            'product_id'          => $movement->product_id,
            'batch_id'            => $movement->batch_id,
            'location_from_id'    => $movement->location_from_id,
            'location_to_id'      => $movement->location_to_id,
            'movement_type'       => $movement->movement_type,
            'quantity'            => $movement->quantity,
            'reason'              => $movement->reason,
            'reference_type'      => $movement->reference_type,
            'reference_id'        => $movement->reference_id,
            'cost_center_id'      => $movement->cost_center_id,
            'service_id'          => $movement->service_id,
            'patient_document'    => $movement->patient_document,
            'patient_external_id' => $movement->patient_external_id,
            'user_id'             => $movement->user_id,
            'created_at'          => $movement->created_at?->toIso8601String(),
            'product_name'        => $movement->relationLoaded('product') ? $movement->product->name : null,
            'batch_lot_number'    => $movement->relationLoaded('batch') && $movement->batch
                ? $movement->batch->lot_number
                : null,
            'user_name'           => $movement->relationLoaded('user') && $movement->user
                ? $movement->user->name
                : null,
            'warehouse_to_name'   => $movement->relationLoaded('warehouseTo') && $movement->warehouseTo
                ? $movement->warehouseTo->name
                : null,
            'cost_center'         => $movement->relationLoaded('costCenter') && $movement->costCenter
                ? ['id' => $movement->costCenter->id, 'code' => $movement->costCenter->code, 'name' => $movement->costCenter->name, 'type' => $movement->costCenter->type]
                : null,
            'medical_service'     => $movement->relationLoaded('medicalService') && $movement->medicalService
                ? ['id' => $movement->medicalService->id, 'code' => $movement->medicalService->code, 'name' => $movement->medicalService->name]
                : null,
        ];
    }
}
