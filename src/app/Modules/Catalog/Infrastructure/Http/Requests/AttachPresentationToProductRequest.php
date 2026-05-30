<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachPresentationToProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_purchase_default' => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ];
    }
}
