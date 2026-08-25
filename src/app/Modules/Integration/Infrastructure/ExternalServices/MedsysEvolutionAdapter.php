<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use App\Modules\Integration\Domain\Ports\MedsysEvolutionServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MedsysEvolutionAdapter implements MedsysEvolutionServiceInterface
{
    // Punto de adaptación: cuando MedSys confirme el nombre real de la tabla,
    // solo hay que actualizar la variable de entorno MEDSYS_EVOLUTION_TABLE.
    private string $table;

    public function __construct()
    {
        $this->table = config('sga.integrations.medsys.evolution_table', 'sga_evoluciones');
    }

    public function isEnabled(): bool
    {
        return config('sga.integrations.medsys.enabled', false) === true
            && config('database.connections.medsys.database') !== null;
    }

    public function pushEvolution(
        string $medsysPatientId,
        string $controlCode,
        string $date,
        string $time,
        string $evolutionText,
        string $user,
        int    $evolutionId,
    ): void {
        DB::connection('medsys')->table($this->table)->updateOrInsert(
            ['sga_evolucion_id' => $evolutionId],
            [
                'idpaciente' => $medsysPatientId,
                'codcontrol' => $controlCode,
                'fecha'      => $date,
                'hora'       => $time,
                'evolucion'  => $evolutionText,
                'usuario'    => $user,
                'creado_en'  => now()->toDateTimeString(),
            ]
        );

        Log::info("MedSys: evolución #{$evolutionId} sincronizada para paciente {$medsysPatientId}.");
    }
}
