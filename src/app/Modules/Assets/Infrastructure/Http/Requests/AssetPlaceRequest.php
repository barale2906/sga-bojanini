<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetPlaceData;
use Illuminate\Foundation\Http\FormRequest;

class AssetPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id'   => ['required', 'integer', 'exists:asset_sites,id'],
            'name'      => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): AssetPlaceData
    {
        return new AssetPlaceData(
            siteId:   (int) $this->validated('site_id'),
            name:     $this->validated('name'),
            isActive: (bool) $this->validated('is_active', true),
        );
    }
}
