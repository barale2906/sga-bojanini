<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $supplier = $this->resource;

        if (method_exists($supplier, 'getId')) {
            return [
                'id'           => $supplier->getId(),
                'name'         => $supplier->getName(),
                'tax_id'       => $supplier->getTaxId(),
                'contact_name' => $supplier->getContactName(),
                'phone'        => $supplier->getPhone(),
                'email'        => $supplier->getEmail(),
                'address'      => $supplier->getAddress(),
                'notes'        => $supplier->getNotes(),
                'is_active'    => $supplier->isActive(),
            ];
        }

        return [
            'id'           => $supplier->id,
            'name'         => $supplier->name,
            'tax_id'       => $supplier->tax_id,
            'contact_name' => $supplier->contact_name,
            'phone'        => $supplier->phone,
            'email'        => $supplier->email,
            'address'      => $supplier->address,
            'notes'        => $supplier->notes,
            'is_active'    => (bool) $supplier->is_active,
            'created_at'   => $supplier->created_at?->toIso8601String(),
        ];
    }
}
