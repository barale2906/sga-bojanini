<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetSiteData;
use Illuminate\Foundation\Http\FormRequest;

class AssetSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:120'],
            'address'   => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): AssetSiteData
    {
        return new AssetSiteData(
            name:     $this->validated('name'),
            address:  $this->validated('address'),
            isActive: (bool) $this->validated('is_active', true),
        );
    }
}
