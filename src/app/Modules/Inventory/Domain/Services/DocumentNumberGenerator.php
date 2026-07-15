<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    private const PREFIXES = [
        'exit'                 => 'SAL',
        'entry'                => 'ENT',
        'transfer'             => 'TRA',
        'adjustment'           => 'AJU',
        'return'               => 'DEV',
        'expiration_write_off' => 'VEN',
        'loss'                 => 'BAJ',
    ];

    /**
     * Genera el siguiente número de documento del tipo dado.
     * Debe ejecutarse dentro de una transacción DB.
     * Retorna formato: SAL-2026-000001
     */
    public function next(string $documentType): string
    {
        $prefix = self::PREFIXES[$documentType]
            ?? throw new \DomainException("Tipo de documento desconocido: {$documentType}");

        $year = (int) now()->format('Y');

        DB::table('document_sequences')
            ->insertOrIgnore(['document_type' => $documentType, 'year' => $year, 'last_number' => 0]);

        $next = DB::table('document_sequences')
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_number') + 1;

        DB::table('document_sequences')
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->update(['last_number' => $next]);

        return sprintf('%s-%d-%06d', $prefix, $year, $next);
    }
}
