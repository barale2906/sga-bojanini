<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['comment' => ['required', 'string', 'max:2000']];
    }
}
