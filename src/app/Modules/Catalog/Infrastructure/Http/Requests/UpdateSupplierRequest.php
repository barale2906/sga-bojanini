<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('supplier');

        return [
            'name'          => ['required', 'string', 'max:255'],
            'tax_id'        => ['nullable', 'string', 'max:50', "unique:suppliers,tax_id,{$id}"],
            'contact_name'  => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'email'         => ['nullable', 'email', 'max:255'],
            'address'       => ['nullable', 'string'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'tax_id.unique' => 'Ya existe un proveedor con este NIT/RUT.',
            'email.email'   => 'El correo electrónico no es válido.',
        ];
    }
}
