<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertPresentationToBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'presentation_id' => ['required', 'integer', 'exists:product_presentations,id'],
            'quantity'        => ['required', 'integer', 'min:1'],
        ];
    }
}
