<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePresentationHierarchyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'factor_to_base'      => ['required', 'integer', 'min:1'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'level'               => ['required', 'integer', 'min:1'],
        ];
    }
}
