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
            'product_id'                => ['required', 'integer', 'exists:products,id'],
            'warehouse_id'              => ['required', 'integer', 'exists:warehouses,id'],
            'location_id'               => ['required', 'integer', 'exists:locations,id'],
            'lot_number'                => ['required', 'string', 'max:100'],
            'expiration_date'           => ['required', 'date'],
            'manufacturing_date'        => ['nullable', 'date'],
            'quantity_base'             => ['nullable', 'integer', 'min:1', 'required_without_all:product_presentation_id,quantity_in_presentation'],
            'product_presentation_id'   => ['nullable', 'integer', 'exists:product_presentations,id', 'required_with:quantity_in_presentation'],
            'quantity_in_presentation'  => ['nullable', 'integer', 'min:1', 'required_with:product_presentation_id'],
            'notes'                     => ['nullable', 'string'],
            'invoice_number'            => ['nullable', 'string', 'max:100'],
            'entry_temperature'         => ['nullable', 'numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasBase = $this->filled('quantity_base');
            $hasPresentation = $this->filled('product_presentation_id') && $this->filled('quantity_in_presentation');

            if (! $hasBase && ! $hasPresentation) {
                $validator->errors()->add(
                    'quantity_base',
                    'Debe indicar quantity_base o product_presentation_id con quantity_in_presentation.'
                );
            }
        });
    }
}
