<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMovementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from'        => ['nullable', 'date_format:Y-m-d'],
            'date_to'          => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'warehouse_id'     => ['nullable', 'integer'],
            'product_id'       => ['nullable', 'integer'],
            'movement_type'    => ['nullable', 'string'],
            'cost_center_id'   => ['nullable', 'integer'],
            'cost_center_type' => ['nullable', 'string', 'in:internal,external'],
            'warehouse_to_id'  => ['nullable', 'integer'],
            'per_page'         => ['nullable', 'integer', 'min:1'],
        ];
    }
}
