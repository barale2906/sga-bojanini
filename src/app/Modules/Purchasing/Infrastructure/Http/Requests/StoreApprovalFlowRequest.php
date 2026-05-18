<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'entity_type' => ['required', 'string', 'max:100'],
            'conditions'  => ['nullable', 'array'],
            'is_active'   => ['sometimes', 'boolean'],
            'steps'       => ['required', 'array', 'min:1'],
            'steps.*.step_order'  => ['required', 'integer', 'min:1'],
            'steps.*.role_id'     => ['required', 'integer', 'exists:roles,id'],
            'steps.*.is_required' => ['sometimes', 'boolean'],
        ];
    }
}
