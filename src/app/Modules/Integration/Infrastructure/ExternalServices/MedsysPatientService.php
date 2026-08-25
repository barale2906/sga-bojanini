<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MedsysPatientService
{
    private const NAME_EXPR = "CONCAT(TRIM(nombre1),' ',TRIM(COALESCE(nombre2,'')),' ',TRIM(apellido1),' ',TRIM(COALESCE(apellido2,'')))";

    public function findByDocument(string $document): ?object
    {
        return DB::connection('medsys')
            ->table('pacientes')
            ->where('documento', $document)
            ->select(
                'codigo',
                'tipodoc',
                'documento',
                DB::raw(self::NAME_EXPR.' as nombre'),
            )
            ->first();
    }

    /** @return Collection<int, object> */
    public function findByName(string $name): Collection
    {
        return DB::connection('medsys')
            ->table('pacientes')
            ->whereRaw(self::NAME_EXPR.' LIKE ?', ['%'.$name.'%'])
            ->select(
                'codigo',
                'tipodoc',
                'documento',
                DB::raw(self::NAME_EXPR.' as nombre'),
            )
            ->limit(20)
            ->get();
    }

    /** @return Collection<int, object> */
    public function listProcedureTypes(): Collection
    {
        return DB::connection('medsys')
            ->table('tiposproc')
            ->where('activo', 1)
            ->select('codigo', 'descripcion')
            ->orderBy('descripcion')
            ->get();
    }
}
