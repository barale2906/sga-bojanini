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
                'product_variant_id'          => $movement->getProductVariantId(),
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
            'id'                   => $movement->id,
            'movement_document_id' => $movement->movement_document_id,
            'warehouse_id'         => $movement->warehouse_id,
            'warehouse_to_id'      => $movement->warehouse_to_id,
            'product_variant_id'   => $movement->product_variant_id,
            'batch_id'            => $movement->batch_id,
            'location_from_id'    => $movement->location_from_id,
            'location_to_id'      => $movement->location_to_id,
            'movement_type'       => $movement->movement_type,
            'quantity'            => $movement->quantity,
            'reason'              => $movement->reason,
            'movement_date'       => $movement->movement_date?->toIso8601String(),
            'reference_type'      => $movement->reference_type,
            'reference_id'        => $movement->reference_id,
            'cost_center_id'      => $movement->cost_center_id,
            'service_id'          => $movement->service_id,
            'patient_document'    => $movement->patient_document,
            'patient_external_id' => $movement->patient_external_id,
            'invoice_number'      => $movement->invoice_number,
            'entry_temperature'   => $movement->entry_temperature,
            'user_id'             => $movement->user_id,
            'status'              => $movement->status instanceof \App\Modules\Inventory\Domain\ValueObjects\MovementStatus
                ? $movement->status->value
                : $movement->status,
            'created_at'          => $movement->created_at?->toIso8601String(),
            'variant_lab_brand'   => $movement->relationLoaded('variant') && $movement->variant ? $movement->variant->lab_brand : null,
            'product_name'        => $movement->relationLoaded('variant') && $movement->variant
                && $movement->variant->relationLoaded('genericProduct') && $movement->variant->genericProduct
                ? $movement->variant->genericProduct->name
                : null,
            'category_id'         => $movement->relationLoaded('variant') && $movement->variant
                && $movement->variant->relationLoaded('genericProduct') && $movement->variant->genericProduct
                && $movement->variant->genericProduct->relationLoaded('category') && $movement->variant->genericProduct->category
                ? $movement->variant->genericProduct->category->id
                : null,
            'category_name'       => $movement->relationLoaded('variant') && $movement->variant
                && $movement->variant->relationLoaded('genericProduct') && $movement->variant->genericProduct
                && $movement->variant->genericProduct->relationLoaded('category') && $movement->variant->genericProduct->category
                ? $movement->variant->genericProduct->category->name
                : null,
            'batch_lot_number'      => $movement->relationLoaded('batch') && $movement->batch
                ? $movement->batch->lot_number
                : null,
            'batch_expiration_date' => $movement->relationLoaded('batch') && $movement->batch
                ? $movement->batch->expiration_date
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
            'signatures'          => $movement->relationLoaded('signatures')
                ? MovementSignatureResource::collection($movement->signatures)
                : null,
        ];
    }
}
