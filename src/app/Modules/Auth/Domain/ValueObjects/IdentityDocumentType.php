<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObjects;

/**
 * Tipos de documento de identidad admitidos para la firma registrada.
 */
enum IdentityDocumentType: string
{
    case CC  = 'CC';
    case CE  = 'CE';
    case TI  = 'TI';
    case PA  = 'PA';
    case PPT = 'PPT';

    public function label(): string
    {
        return match ($this) {
            self::CC  => 'Cédula de ciudadanía',
            self::CE  => 'Cédula de extranjería',
            self::TI  => 'Tarjeta de identidad',
            self::PA  => 'Pasaporte',
            self::PPT => 'Permiso por protección temporal',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
