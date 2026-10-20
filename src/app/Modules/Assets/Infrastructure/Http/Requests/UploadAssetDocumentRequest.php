<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\AssetDocumentData;
use App\Modules\Assets\Domain\Enums\AssetDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadAssetDocumentRequest extends FormRequest
{
    /** Tamaño máximo por archivo en KB (10 MB). */
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'           => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . self::MAX_KB],
            'document_type'  => ['required', Rule::enum(AssetDocumentType::class)],
            'title'          => ['required', 'string', 'max:150'],
            'maintenance_id' => ['nullable', 'integer'],
            'issued_at'      => ['nullable', 'date'],
            'expires_at'     => ['nullable', 'date', 'after_or_equal:issued_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Solo se admiten archivos PDF, JPG o PNG.',
            'file.max'   => 'El archivo no puede superar 10 MB.',
        ];
    }

    public function toData(): AssetDocumentData
    {
        return new AssetDocumentData(
            file:          $this->file('file'),
            documentType:  $this->validated('document_type'),
            title:         $this->validated('title'),
            maintenanceId: $this->validated('maintenance_id') !== null ? (int) $this->validated('maintenance_id') : null,
            issuedAt:      $this->validated('issued_at'),
            expiresAt:     $this->validated('expires_at'),
        );
    }
}
