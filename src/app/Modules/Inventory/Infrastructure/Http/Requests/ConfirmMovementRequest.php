<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use App\Modules\Auth\Application\Services\SignaturePinService;
use App\Modules\Inventory\Application\DTOs\SignerInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmación de un comprobante con las firmas de quien entrega y quien recibe.
 *
 * - `delivered_by` es opcional: si se omite se usa la firma registrada del
 *   usuario autenticado. Si se envía, se firma en pad (flujo original).
 * - `received_by` admite `pin` (firma registrada del dueño del PIN) o
 *   `name` + `document` + `signature` (externos o usuarios sin firma
 *   registrada). Si llega `pin`, los demás campos se ignoran.
 */
class ConfirmMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivered_by'            => ['sometimes', 'array'],
            'delivered_by.name'       => ['required_with:delivered_by', 'string', 'max:150'],
            'delivered_by.document'   => ['required_with:delivered_by', 'string', 'max:50'],
            'delivered_by.signature'  => ['required_with:delivered_by', 'string'],

            'received_by'             => ['required', 'array'],
            'received_by.pin'         => ['nullable', 'string', 'digits:' . SignaturePinService::PIN_LENGTH],
            'received_by.name'        => ['exclude_with:received_by.pin', 'required_without:received_by.pin', 'string', 'max:150'],
            'received_by.document'    => ['exclude_with:received_by.pin', 'required_without:received_by.pin', 'string', 'max:50'],
            'received_by.signature'   => ['exclude_with:received_by.pin', 'required_without:received_by.pin', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivered_by.name.required_with'         => 'El nombre de quien entrega es obligatorio.',
            'delivered_by.document.required_with'     => 'El documento de quien entrega es obligatorio.',
            'delivered_by.signature.required_with'    => 'La firma de quien entrega es obligatoria.',
            'received_by.required'                    => 'Los datos de quien recibe son obligatorios.',
            'received_by.pin.digits'                  => 'El PIN de firma debe tener ' . SignaturePinService::PIN_LENGTH . ' dígitos.',
            'received_by.name.required_without'       => 'El nombre de quien recibe es obligatorio.',
            'received_by.document.required_without'   => 'El documento de quien recibe es obligatorio.',
            'received_by.signature.required_without'  => 'La firma de quien recibe es obligatoria.',
        ];
    }

    /**
     * @return array{delivered_by: SignerInput, received_by: SignerInput}
     */
    public function signers(): array
    {
        $validated = $this->validated();
        $delivered = $validated['delivered_by'] ?? null;
        $received  = $validated['received_by'];

        return [
            'delivered_by' => $delivered
                ? SignerInput::pad($delivered['name'], $delivered['document'], $delivered['signature'])
                : SignerInput::stored(),
            'received_by'  => ! empty($received['pin'])
                ? SignerInput::pin($received['pin'])
                : SignerInput::pad($received['name'], $received['document'], $received['signature']),
        ];
    }
}
