<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

class CodeGeneratorService
{
    /**
     * Genera un código legible a partir de un nombre.
     * Transliteración de acentos → mayúsculas → solo alfanumérico y guiones.
     * Si el resultado queda vacío devuelve 'COD'.
     */
    public static function fromName(string $name, int $maxLength = 50): string
    {
        $code = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $code = strtoupper($code);
        $code = preg_replace('/[^A-Z0-9\s]/', '', $code) ?? '';
        $code = preg_replace('/\s+/', '-', trim($code)) ?? '';
        $code = trim($code, '-');
        $code = substr($code, 0, $maxLength);

        return $code !== '' ? $code : 'COD';
    }
}
