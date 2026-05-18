<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('unit_of_measure');

        return [
            'name'         => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:10', "unique:units_of_measure,abbreviation,{$id}"],
            'is_base'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'         => 'El nombre de la unidad de medida es obligatorio.',
            'abbreviation.required' => 'La abreviatura es obligatoria.',
            'abbreviation.unique'   => 'Ya existe una unidad de medida con esta abreviatura.',
        ];
    }
}
