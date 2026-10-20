<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Auth\Infrastructure\Http\Requests\Concerns\ValidatesSigners;
use Illuminate\Foundation\Http\FormRequest;

/** Confirmación de un traslado pendiente: firma obligatoria de entrega y recibe. */
class ConfirmAssetTransferRequest extends FormRequest
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
