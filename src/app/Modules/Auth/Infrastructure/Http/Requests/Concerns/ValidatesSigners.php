<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests\Concerns;

use App\Modules\Auth\Application\DTOs\SignerInput;
use App\Modules\Auth\Application\Services\SignaturePinService;

/**
 * Reglas, mensajes y mapeo a {@see SignerInput} de los firmantes de un
 * documento (quien entrega y quien recibe), compartidos por todos los
 * FormRequest que capturan firmas.
 *
 * - `delivered_by` es opcional: si se omite se usa la firma registrada del
 *   usuario autenticado. Si se envía, se firma en pad.
 * - `received_by` admite `pin` (firma registrada del dueño del PIN) o
 *   `name` + `document` + `signature` (externos o usuarios sin firma
 *   registrada). Si llega `pin`, los demás campos se ignoran.
 */
trait ValidatesSigners
{
    /**
     * @param bool $receiverRequired false cuando la firma es opcional: si no
     *                               llega `received_by` el documento no se firma.
     */
    protected function signerRules(bool $receiverRequired = true): array
    {
        return [
            'delivered_by'            => ['sometimes', 'array'],
            'delivered_by.name'       => ['required_with:delivered_by', 'string', 'max:150'],
            'delivered_by.document'   => ['required_with:delivered_by', 'string', 'max:50'],
            'delivered_by.signature'  => ['required_with:delivered_by', 'string'],

            'received_by'             => [$receiverRequired ? 'required' : 'sometimes', 'array'],
            'received_by.pin'         => ['nullable', 'string', 'digits:' . SignaturePinService::PIN_LENGTH],
            'received_by.name'        => ['exclude_without:received_by', 'exclude_with:received_by.pin', 'required', 'string', 'max:150'],
            'received_by.document'    => ['exclude_without:received_by', 'exclude_with:received_by.pin', 'required', 'string', 'max:50'],
            'received_by.signature'   => ['exclude_without:received_by', 'exclude_with:received_by.pin', 'required', 'string'],
        ];
    }

    protected function signerMessages(): array
    {
        return [
            'delivered_by.name.required_with'         => 'El nombre de quien entrega es obligatorio.',
            'delivered_by.document.required_with'     => 'El documento de quien entrega es obligatorio.',
            'delivered_by.signature.required_with'    => 'La firma de quien entrega es obligatoria.',
            'received_by.required'                    => 'Los datos de quien recibe son obligatorios.',
            'received_by.pin.digits'                  => 'El PIN de firma debe tener ' . SignaturePinService::PIN_LENGTH . ' dígitos.',
            'received_by.name.required'               => 'El nombre de quien recibe es obligatorio.',
            'received_by.document.required'           => 'El documento de quien recibe es obligatorio.',
            'received_by.signature.required'          => 'La firma de quien recibe es obligatoria.',
        ];
    }

    /**
     * Firmantes enviados, o null si la firma es opcional y no se envió `received_by`.
     *
     * @return array{delivered_by: SignerInput, received_by: SignerInput}|null
     */
    public function signers(): ?array
    {
        $validated = $this->validated();
        $received  = $validated['received_by'] ?? null;

        if ($received === null) {
            return null;
        }

        $delivered = $validated['delivered_by'] ?? null;

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
