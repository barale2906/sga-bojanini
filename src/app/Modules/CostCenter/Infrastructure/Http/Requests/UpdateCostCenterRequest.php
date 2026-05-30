<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use App\Modules\CostCenter\Domain\Enums\CostCenterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('cost_center');

        return [
            'code'        => ['required', 'string', 'max:20', Rule::unique('cost_centers', 'code')->ignore($id)],
            'name'        => ['required', 'string', 'max:100'],
            'type'        => ['required', Rule::enum(CostCenterType::class)],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
