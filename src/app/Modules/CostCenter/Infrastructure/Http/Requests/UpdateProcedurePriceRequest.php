<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedurePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_price'     => ['required', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'is_active'      => ['sometimes', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_to.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a la fecha de vigencia.',
        ];
    }
}
