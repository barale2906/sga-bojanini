<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\DTOs;

/**
 * Contenido binario de un reporte ya generado, listo para enviarse al
 * navegador (descarga directa) o persistirse temporalmente en disco
 * (flujo en background). Los exporters nunca escriben a disco por sí
 * mismos: solo producen este DTO en memoria.
 */
final class GeneratedReportFile
{
    private function __construct(
        public readonly string $content,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly string $filename,
    ) {}

    public static function make(string $reportType, string $extension, string $mimeType, string $content): self
    {
        $filename = sprintf('%s_%s.%s', $reportType, now()->format('Ymd_His'), $extension);

        return new self($content, $extension, $mimeType, $filename);
    }

    public function sizeInBytes(): int
    {
        return strlen($this->content);
    }
}
