<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida los parámetros de generación de cualquiera de los reportes
 * disponibles en /reports. Las reglas específicas de cada tipo se
 * seleccionan según el método de acción de la ruta (inventory, movements,
 * expiring, purchases, consumption, audit, conditions).
 */
class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            [
                'format' => ['sometimes', 'string', Rule::in(['pdf', 'excel', 'csv'])],
            ],
            $this->typeSpecificRules(),
        );
    }

    public function messages(): array
    {
        return [
            'format.in'             => 'El formato debe ser pdf, excel o csv.',
            'warehouse_id.exists'   => 'El almacén seleccionado no existe.',
            'category_id.exists'    => 'La categoría seleccionada no existe.',
            'date_from.date'        => 'La fecha inicial no es válida.',
            'date_to.date'          => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'days.integer'          => 'Los días deben ser un número entero.',
            'days.min'              => 'Los días deben ser al menos 1.',
            'days.max'              => 'Los días no pueden superar 365.',
            'user_id.exists'        => 'El usuario seleccionado no existe.',
        ];
    }

    private function typeSpecificRules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'inventory' => [
                'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
                'category_id'  => ['sometimes', 'integer', 'exists:categories,id'],
            ],
            'movements' => [
                'date_from' => ['sometimes', 'date'],
                'date_to'   => ['sometimes', 'date', 'after_or_equal:date_from'],
                'type'      => ['sometimes', 'string'],
            ],
            'expiring' => [
                'days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            ],
            'purchases' => [
                'status'    => ['sometimes', 'string'],
                'date_from' => ['sometimes', 'date'],
                'date_to'   => ['sometimes', 'date', 'after_or_equal:date_from'],
            ],
            'consumption' => [
                'date_from' => ['sometimes', 'date'],
                'date_to'   => ['sometimes', 'date', 'after_or_equal:date_from'],
            ],
            'audit' => [
                'user_id'   => ['sometimes', 'integer', 'exists:users,id'],
                'action'    => ['sometimes', 'string'],
                'date_from' => ['sometimes', 'date'],
                'date_to'   => ['sometimes', 'date', 'after_or_equal:date_from'],
            ],
            default => [],
        };
    }
}
