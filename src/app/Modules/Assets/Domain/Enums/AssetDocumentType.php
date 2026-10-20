<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetDocumentType: string
{
    case Manual                 = 'manual';
    case Invoice                = 'invoice';
    case Warranty               = 'warranty';
    case Invima                 = 'invima';
    case CalibrationCertificate = 'calibration_certificate';
    case ServiceReport          = 'service_report';
    case Photo                  = 'photo';
    case Protocol               = 'protocol';
    case Other                  = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Manual                 => 'Manual',
            self::Invoice                => 'Factura',
            self::Warranty               => 'Garantía',
            self::Invima                 => 'Registro INVIMA',
            self::CalibrationCertificate => 'Certificado de calibración',
            self::ServiceReport          => 'Reporte de servicio',
            self::Photo                  => 'Fotografía',
            self::Protocol               => 'Protocolo',
            self::Other                  => 'Otro',
        };
    }
}
