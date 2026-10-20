<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetTransferRequestData;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Auth\Infrastructure\Http\Requests\Concerns\ValidatesSigners;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de traslado. Las firmas son opcionales aquí: si el tipo exige
 * firma y no se envían, el traslado queda pendiente de confirmación.
 */
class RequestAssetTransferRequest extends FormRequest
{
    use ValidatesSigners;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = array_map(fn ($t) => $t->value, AssetTransferType::requestable());

        return [
            'type'                 => ['required', Rule::in($types)],
            'to_place_id'          => ['nullable', 'integer', 'exists:asset_places,id', 'required_if:type,return', 'required_without_all:to_responsible_id,external_destination,reason'],
            'to_responsible_id'    => ['nullable', 'integer', 'exists:users,id'],
            'external_destination' => ['nullable', 'string', 'max:200', 'required_if:type,external_repair', 'required_if:type,loan'],
            'supplier_id'          => ['nullable', 'integer', 'exists:suppliers,id'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:today'],
            'resulting_status'     => ['nullable', Rule::in(array_map(fn ($s) => $s->value, AssetStatus::manuallySettable()))],
            'reason'               => ['nullable', 'string', 'max:1000', 'required_if:type,retirement'],
            ...$this->signerRules(receiverRequired: false),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->signerMessages(),
            'to_place_id.required_if'          => 'Indique el sitio al que retorna el activo.',
            'to_place_id.required_without_all' => 'Indique el sitio destino o el nuevo responsable.',
            'external_destination.required_if' => 'Indique el destino fuera de las instalaciones (taller, entidad, etc.).',
            'reason.required_if'               => 'El motivo de la baja es obligatorio.',
        ];
    }

    public function toData(): AssetTransferRequestData
    {
        return AssetTransferRequestData::fromArray($this->validated());
    }
}
