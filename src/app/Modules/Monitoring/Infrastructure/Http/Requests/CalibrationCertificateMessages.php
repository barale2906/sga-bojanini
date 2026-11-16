<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

/**
 * Mensajes en español compartidos por la carga de certificados, tanto en
 * su endpoint propio como al crear el sensor (campos con prefijo
 * `certificate_`). No hay lang/es en el proyecto.
 */
final class CalibrationCertificateMessages
{
    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}file.required"       => 'Adjunte el archivo del certificado de calibración.',
            "{$prefix}file.mimes"          => 'El certificado debe ser PDF, JPG o PNG.',
            "{$prefix}file.max"            => 'El certificado no puede superar 10 MB.',
            "{$prefix}expires_at.required" => 'Indique la fecha de vencimiento del certificado.',
            "{$prefix}expires_at.after_or_equal" => 'El vencimiento debe ser posterior a la fecha de calibración.',
            "{$prefix}calibrated_at.before_or_equal" => 'La fecha de calibración no puede ser futura.',
        ];
    }

    public static function attributes(string $prefix = ''): array
    {
        return [
            "{$prefix}file"               => 'certificado',
            "{$prefix}expires_at"         => 'fecha de vencimiento',
            "{$prefix}calibrated_at"      => 'fecha de calibración',
            "{$prefix}certificate_number" => 'número de certificado',
            "{$prefix}laboratory"         => 'laboratorio',
            "{$prefix}notes"              => 'observaciones',
        ];
    }
}
