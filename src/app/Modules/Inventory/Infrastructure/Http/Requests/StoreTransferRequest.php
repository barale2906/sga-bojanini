<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_from_id'            => ['required', 'integer', 'exists:warehouses,id'],
            'warehouse_to_id'              => ['required', 'integer', 'exists:warehouses,id'],
            'movement_date'                => ['nullable', 'date', 'before_or_equal:today'],
            'reason'                       => ['nullable', 'string'],

            'items'                        => ['required', 'array', 'min:1'],
            'items.*.product_variant_id'   => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.location_from_id'     => ['required', 'integer', 'exists:locations,id'],
            'items.*.location_to_id'       => ['required', 'integer', 'exists:locations,id'],
            'items.*.quantity'             => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $warehouseFromId = (int) $this->input('warehouse_from_id');
            $warehouseToId   = (int) $this->input('warehouse_to_id');

            foreach ((array) $this->input('items', []) as $i => $item) {
                $locationFromId = (int) ($item['location_from_id'] ?? 0);
                $locationToId   = (int) ($item['location_to_id'] ?? 0);

                if ($warehouseFromId === $warehouseToId && $locationFromId === $locationToId) {
                    $v->errors()->add("items.{$i}.location_to_id", 'La ubicación de destino debe ser diferente a la de origen.');
                    continue;
                }

                $fromWarehouse = DB::table('locations')
                    ->join('zones', 'locations.zone_id', '=', 'zones.id')
                    ->where('locations.id', $locationFromId)
                    ->value('zones.warehouse_id');

                if ((int) $fromWarehouse !== $warehouseFromId) {
                    $v->errors()->add("items.{$i}.location_from_id", 'La ubicación de origen no pertenece al almacén de origen.');
                }

                $toWarehouse = DB::table('locations')
                    ->join('zones', 'locations.zone_id', '=', 'zones.id')
                    ->where('locations.id', $locationToId)
                    ->value('zones.warehouse_id');

                if ((int) $toWarehouse !== $warehouseToId) {
                    $v->errors()->add("items.{$i}.location_to_id", 'La ubicación de destino no pertenece al almacén de destino.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required'                       => 'Debe incluir al menos un ítem.',
            'items.min'                            => 'Debe incluir al menos un ítem.',
            'items.*.product_variant_id.required'  => 'La variante de producto es obligatoria en cada ítem.',
            'items.*.product_variant_id.exists'    => 'Una de las variantes no existe.',
            'items.*.location_from_id.required'    => 'La ubicación de origen es obligatoria en cada ítem.',
            'items.*.location_to_id.required'      => 'La ubicación de destino es obligatoria en cada ítem.',
            'items.*.quantity.required'            => 'La cantidad es obligatoria en cada ítem.',
            'items.*.quantity.min'                 => 'La cantidad debe ser mayor a cero.',
        ];
    }
}
