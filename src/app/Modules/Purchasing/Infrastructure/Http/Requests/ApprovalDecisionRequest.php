<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApprovalDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'record_id' => ['nullable', 'integer', 'exists:approval_records,id'],
            'comments'  => ['nullable', 'string'],
        ];
    }
}
