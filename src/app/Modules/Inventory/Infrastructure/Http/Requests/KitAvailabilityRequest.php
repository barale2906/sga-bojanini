<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KitAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kit_product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('product_type', ProductType::Kit->value),
            ],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'kit_product_id.exists' => 'El producto indicado no existe o no es de tipo kit.',
        ];
    }
}
