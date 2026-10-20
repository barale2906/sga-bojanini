<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetData;
use App\Modules\Assets\Infrastructure\Http\Requests\Concerns\AssetDetailRules;
use Illuminate\Foundation\Http\FormRequest;

/** Edición de datos generales (ubicación, responsable y estado cambian por sus propios flujos). */
class UpdateAssetRequest extends FormRequest
{
    use AssetDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->detailRules();
    }

    public function toData(): AssetData
    {
        return AssetData::fromArray($this->validated());
    }
}
