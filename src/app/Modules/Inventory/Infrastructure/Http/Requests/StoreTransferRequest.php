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
            'product_id'        => ['required', 'integer', 'exists:products,id'],
            'warehouse_from_id' => ['required', 'integer', 'exists:warehouses,id'],
            'warehouse_to_id'   => ['required', 'integer', 'exists:warehouses,id'],
            'location_from_id'  => ['required', 'integer', 'exists:locations,id'],
            'location_to_id'    => ['required', 'integer', 'exists:locations,id'],
            'quantity'          => ['required', 'integer', 'min:1'],
            'reason'            => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $locationFromId  = (int) $this->input('location_from_id');
            $locationToId    = (int) $this->input('location_to_id');
            $warehouseFromId = (int) $this->input('warehouse_from_id');
            $warehouseToId   = (int) $this->input('warehouse_to_id');

            if ($warehouseFromId === $warehouseToId && $locationFromId === $locationToId) {
                $v->errors()->add('location_to_id', 'La ubicación de destino debe ser diferente a la de origen.');

                return;
            }

            $fromWarehouse = DB::table('locations')
                ->join('zones', 'locations.zone_id', '=', 'zones.id')
                ->where('locations.id', $locationFromId)
                ->value('zones.warehouse_id');

            if ((int) $fromWarehouse !== $warehouseFromId) {
                $v->errors()->add('location_from_id', 'La ubicación de origen no pertenece al almacén de origen.');
            }

            $toWarehouse = DB::table('locations')
                ->join('zones', 'locations.zone_id', '=', 'zones.id')
                ->where('locations.id', $locationToId)
                ->value('zones.warehouse_id');

            if ((int) $toWarehouse !== $warehouseToId) {
                $v->errors()->add('location_to_id', 'La ubicación de destino no pertenece al almacén de destino.');
            }
        });
    }
}
