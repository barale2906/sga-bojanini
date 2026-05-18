<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:scheduling,clinical_records'],
            'base_url'    => ['required', 'url', 'max:500'],
            'auth_type'   => ['required', 'in:bearer,api_key,oauth2,basic'],
            'credentials' => ['required', 'array'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
