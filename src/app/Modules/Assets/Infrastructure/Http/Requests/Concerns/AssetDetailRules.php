<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests\Concerns;

/** Reglas de los datos generales de un activo, comunes a registro y edición. */
trait AssetDetailRules
{
    protected function detailRules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:200'],
            'category_id'       => ['required', 'integer', 'exists:asset_categories,id'],
            'description'       => ['nullable', 'string'],
            'brand'             => ['nullable', 'string', 'max:100'],
            'model'             => ['nullable', 'string', 'max:100'],
            'serial'            => ['nullable', 'string', 'max:100'],
            'acquisition_date'  => ['nullable', 'date', 'before_or_equal:today'],
            'acquisition_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'supplier_id'       => ['nullable', 'integer', 'exists:suppliers,id'],
            'invoice_number'    => ['nullable', 'string', 'max:50'],
            'warranty_until'    => ['nullable', 'date'],
            'notes'             => ['nullable', 'string'],
        ];
    }
}
