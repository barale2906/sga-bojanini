<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetData;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Infrastructure\Http\Requests\Concerns\AssetDetailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Registro de un activo: datos generales + ubicación, responsable y estado iniciales. */
class StoreAssetRequest extends FormRequest
{
    use AssetDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->detailRules(),
            'place_id'       => ['required', 'integer', 'exists:asset_places,id'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
            'status'         => ['nullable', Rule::in(array_map(fn ($s) => $s->value, AssetStatus::manuallySettable()))],
            'comment'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): AssetData
    {
        return AssetData::fromArray($this->validated());
    }
}
