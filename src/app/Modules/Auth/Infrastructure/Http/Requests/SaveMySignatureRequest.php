<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use App\Modules\Auth\Application\DTOs\SaveSignatureData;
use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro o actualización de la firma del usuario autenticado.
 *
 * Exige la contraseña actual: si alguien deja la sesión abierta, otra persona
 * no puede reemplazar su firma.
 */
class SaveMySignatureRequest extends FormRequest
{
    /** Tamaño máximo de la imagen en base64 (~512 KB). */
    private const MAX_SIGNATURE_LENGTH = 700000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'document_type'    => ['required', 'string', Rule::in(IdentityDocumentType::values())],
            'document_number'  => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'signature'        => ['required', 'string', 'max:' . self::MAX_SIGNATURE_LENGTH, 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'         => 'Debe ingresar su contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'document_type.required'            => 'El tipo de documento es obligatorio.',
            'document_type.in'                  => 'El tipo de documento no es válido.',
            'document_number.required'          => 'El número de documento es obligatorio.',
            'document_number.min'               => 'El número de documento debe tener al menos 4 caracteres.',
            'document_number.max'               => 'El número de documento no puede superar 20 caracteres.',
            'document_number.regex'             => 'El número de documento solo puede contener letras y números, sin puntos ni espacios.',
            'signature.required'                => 'La firma es obligatoria.',
            'signature.max'                     => 'La imagen de la firma es demasiado grande.',
            'signature.regex'                   => 'La firma debe ser una imagen PNG en base64.',
        ];
    }

    public function toData(): SaveSignatureData
    {
        return new SaveSignatureData(
            documentType: IdentityDocumentType::from($this->validated('document_type')),
            documentNumber: strtoupper($this->validated('document_number')),
            signatureData: $this->validated('signature'),
        );
    }
}
