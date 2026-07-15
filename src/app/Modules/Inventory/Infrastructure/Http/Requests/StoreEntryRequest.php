<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'       => ['required', 'integer', 'exists:warehouses,id'],
            'invoice_number'     => ['nullable', 'string', 'max:100'],
            'entry_temperature'  => ['nullable', 'numeric'],
            'reason'             => ['nullable', 'string'],

            'items'                               => ['required', 'array', 'min:1'],
            'items.*.product_variant_id'          => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.location_id'                 => ['required', 'integer', 'exists:locations,id'],
            'items.*.lot_number'                  => ['required', 'string', 'max:100'],
            'items.*.expiration_date'             => ['required', 'date'],
            'items.*.manufacturing_date'          => ['nullable', 'date'],
            'items.*.quantity_base'               => ['nullable', 'integer', 'min:1'],
            'items.*.product_presentation_id'     => ['nullable', 'integer', 'exists:product_presentations,id'],
            'items.*.quantity_in_presentation'    => ['nullable', 'integer', 'min:1'],
            'items.*.notes'                       => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach ((array) $this->input('items', []) as $i => $item) {
                $hasBase         = ! empty($item['quantity_base']);
                $hasPresentation = ! empty($item['product_presentation_id']) && ! empty($item['quantity_in_presentation']);

                if (! $hasBase && ! $hasPresentation) {
                    $v->errors()->add(
                        "items.{$i}.quantity_base",
                        'Debe indicar quantity_base o product_presentation_id con quantity_in_presentation.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required'                          => 'Debe incluir al menos un ítem.',
            'items.min'                               => 'Debe incluir al menos un ítem.',
            'items.*.product_variant_id.required'     => 'La variante de producto es obligatoria en cada ítem.',
            'items.*.product_variant_id.exists'       => 'Una de las variantes no existe.',
            'items.*.location_id.required'            => 'La ubicación es obligatoria en cada ítem.',
            'items.*.lot_number.required'             => 'El número de lote es obligatorio en cada ítem.',
            'items.*.expiration_date.required'        => 'La fecha de vencimiento es obligatoria en cada ítem.',
        ];
    }
}
