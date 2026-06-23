<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Valida el archivo y el almacén destino opcional de `POST /movements/initial-entries/import`. */
class ImportInitialEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'         => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:10240'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}
