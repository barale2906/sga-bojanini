<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', "unique:users,email,{$userId}"],
            'password'     => ['nullable', 'string', 'min:8'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'is_active'    => ['nullable', 'boolean'],
            'role_ids'     => ['nullable', 'array'],
            'role_ids.*'   => ['integer', 'exists:roles,id'],
        ];
    }
}
