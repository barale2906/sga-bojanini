<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Import;

/**
 * Error de una fila del archivo de importación. No se propaga fuera del
 * servicio: se acumula en el resumen y la importación continúa.
 */
final class RowFailed extends \RuntimeException
{
    /** @param array<string, string[]> $errors campo => mensajes */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Fila con errores.');
    }

    public static function field(string $field, string $message): self
    {
        return new self([$field => [$message]]);
    }
}
