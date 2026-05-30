<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use App\Modules\CostCenter\Domain\Enums\CostCenterType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:20', 'unique:cost_centers,code'],
            'name'        => ['required', 'string', 'max:100'],
            'type'        => ['required', Rule::enum(CostCenterType::class)],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
