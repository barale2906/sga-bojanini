<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetCategoryData;
use App\Modules\Assets\Domain\Enums\AssetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creación y edición de categorías (la unicidad del código la valida el caso de uso). */
class AssetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:20'],
            'name'        => ['required', 'string', 'max:120'],
            'asset_type'  => ['required', Rule::enum(AssetType::class)],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): AssetCategoryData
    {
        return new AssetCategoryData(
            code:        $this->validated('code'),
            name:        $this->validated('name'),
            assetType:   $this->validated('asset_type'),
            description: $this->validated('description'),
            isActive:    (bool) $this->validated('is_active', true),
        );
    }
}
