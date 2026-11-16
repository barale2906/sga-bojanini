<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportReadingsRequest extends FormRequest
{
    /** Periodo máximo exportable en días. */
    public const MAX_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $days = \Carbon\Carbon::parse($this->input('date_from'))->diffInDays(\Carbon\Carbon::parse($this->input('date_to')));

            if ($days > self::MAX_DAYS) {
                $validator->errors()->add('date_to', 'El periodo a exportar no puede superar ' . self::MAX_DAYS . ' días.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'date_from.required'     => 'Indique la fecha inicial del periodo.',
            'date_to.required'       => 'Indique la fecha final del periodo.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ];
    }
}
