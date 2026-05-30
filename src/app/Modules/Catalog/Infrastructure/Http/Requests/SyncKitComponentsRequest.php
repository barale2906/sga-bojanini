<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncKitComponentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'components'                        => ['required', 'array', 'min:1'],
            'components.*.component_product_id' => ['required', 'integer', 'exists:products,id'],
            'components.*.quantity_per_kit'     => ['required', 'integer', 'min:1'],
            'components.*.sort_order'           => ['nullable', 'integer', 'min:0'],
            'components.*.notes'                => ['nullable', 'string', 'max:500'],
        ];
    }
}
