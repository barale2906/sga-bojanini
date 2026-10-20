<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Cancelación de traslados pendientes y órdenes de mantenimiento. */
class CancelWithReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Indique el motivo de la cancelación.'];
    }
}
