<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\DTOs;

/**
 * Cómo firma uno de los roles (entrega / recibe) al confirmar un movimiento.
 *
 * - METHOD_PAD:    nombre, documento y firma capturados en el momento.
 * - METHOD_STORED: firma registrada del usuario autenticado (solo entrega).
 * - METHOD_PIN:    firma registrada del usuario dueño del PIN (solo recibe).
 */
final readonly class SignerInput
{
    public const METHOD_PAD    = 'pad';
    public const METHOD_STORED = 'stored';
    public const METHOD_PIN    = 'pin';

    private function __construct(
        public string $method,
        public ?string $name = null,
        public ?string $document = null,
        public ?string $signature = null,
        public ?string $pin = null,
    ) {}

    public static function pad(string $name, string $document, string $signature): self
    {
        return new self(self::METHOD_PAD, $name, $document, $signature);
    }

    public static function stored(): self
    {
        return new self(self::METHOD_STORED);
    }

    public static function pin(string $pin): self
    {
        return new self(self::METHOD_PIN, pin: $pin);
    }
}
