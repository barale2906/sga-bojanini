<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Integration\Infrastructure\ExternalServices\MedsysPatientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza los tipos de procedimiento de MedSys (tiposproc) con los servicios
 * médicos de SGA, asignando external_code automáticamente cuando hay coincidencia
 * por nombre. Los mapeos ya existentes nunca se modifican.
 *
 * Ejecución manual:  php artisan medsys:sync-procedure-types
 * Con revisión seca: php artisan medsys:sync-procedure-types --dry-run
 */
class SyncMedsysProcedureTypesCommand extends Command
{
    protected $signature = 'medsys:sync-procedure-types
                            {--dry-run : Muestra qué haría sin escribir nada en la BD}';

    protected $description = 'Sincroniza tipos de procedimiento MedSys → external_code en servicios SGA';

    public function handle(MedsysPatientService $medsysService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('--- MODO SIMULACIÓN (--dry-run) — no se escribirá nada ---');
        }

        // 1. Verificar que MedSys está accesible
        try {
            DB::connection('medsys')->getPdo();
        } catch (\Throwable $e) {
            $this->error("No se pudo conectar a MedSys: {$e->getMessage()}");
            Log::error('medsys:sync-procedure-types — conexión fallida', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        // 2. Traer todos los tipos activos de MedSys
        $tipos = $medsysService->listProcedureTypes();

        if ($tipos->isEmpty()) {
            $this->info('MedSys no devolvió tipos de procedimiento activos.');
            return self::SUCCESS;
        }

        $this->info("Tipos encontrados en MedSys: {$tipos->count()}");

        // 3. Índice de servicios SGA por external_code (ya mapeados)
        $yaMapeados = MedicalServiceModel::whereNotNull('external_code')
            ->pluck('external_code')
            ->flip(); // [codigo => true]

        // 4. Índice de servicios SGA por nombre normalizado → id
        $servicesByName = MedicalServiceModel::where('type', 'procedure')
            ->where('is_active', true)
            ->get(['id', 'name'])
            ->keyBy(fn ($s) => $this->normalize($s->name));

        $autoMapped = 0;
        $pending    = [];

        foreach ($tipos as $tipo) {
            // Ya tiene mapeo → saltar
            if ($yaMapeados->has($tipo->codigo)) {
                $this->line("  <fg=green>✓</> {$tipo->codigo} — {$tipo->descripcion} (ya mapeado)");
                continue;
            }

            // Buscar por coincidencia de nombre normalizado
            $key     = $this->normalize($tipo->descripcion);
            $service = $servicesByName->get($key);

            if ($service !== null) {
                $this->line("  <fg=cyan>→</> {$tipo->codigo} — {$tipo->descripcion} → servicio #{$service->id} «{$service->name}»");

                if (! $dryRun) {
                    MedicalServiceModel::where('id', $service->id)
                        ->update(['external_code' => $tipo->codigo]);

                    Log::info('medsys:sync-procedure-types — mapeo automático', [
                        'medsys_codigo'      => $tipo->codigo,
                        'medsys_descripcion' => $tipo->descripcion,
                        'medical_service_id' => $service->id,
                    ]);
                }

                $autoMapped++;
            } else {
                $this->line("  <fg=yellow>?</> {$tipo->codigo} — {$tipo->descripcion} (sin coincidencia, requiere mapeo manual)");
                $pending[] = $tipo->codigo.' — '.$tipo->descripcion;
            }
        }

        // 5. Resumen
        $this->newLine();
        $this->info("Mapeados automáticamente: {$autoMapped}");
        $this->info('Pendientes de mapeo manual: '.count($pending));

        if (! empty($pending)) {
            $this->warn('Los siguientes tipos de MedSys no tienen servicio equivalente en SGA:');
            foreach ($pending as $item) {
                $this->warn("  · {$item}");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Simulación completada — ningún dato fue modificado.');
        }

        return self::SUCCESS;
    }

    /** Normaliza un nombre para comparación: minúsculas, sin tildes, sin espacios extra */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }
}
