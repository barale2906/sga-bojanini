<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use App\Modules\Auth\Infrastructure\Http\Requests\Concerns\ValidatesSigners;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmación de un comprobante con las firmas de quien entrega y quien recibe.
 *
 * Reglas de firmantes en {@see ValidatesSigners}; aquí `received_by` es obligatorio.
 */
class ConfirmMovementRequest extends FormRequest
{
    use ValidatesSigners;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->signerRules(receiverRequired: true);
    }

    public function messages(): array
    {
        return $this->signerMessages();
    }
}
