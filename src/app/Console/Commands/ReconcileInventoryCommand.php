<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Inventory\Application\Services\InventoryReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileInventoryCommand extends Command
{
    protected $signature = 'sga:reconcile-inventory
                            {--fix : Corrige ubicaciones, lotes y resúmenes según el libro de movimientos}';

    protected $description = 'Verifica que el stock por almacén (ubicaciones, lotes y resúmenes) coincida con los movimientos registrados';

    public function handle(InventoryReconciliationService $service): int
    {
        $apply  = (bool) $this->option('fix');
        $report = $service->run($apply);

        $this->renderDiscrepancies($report['discrepancies']);
        $this->renderSummaryMismatches($report['summary_mismatches']);
        $this->renderForeignLocationMovements($report['foreign_location_movements']);

        if ($apply) {
            return $this->renderFixResult($report['unresolved']);
        }

        $issues = count($report['discrepancies']) + count($report['summary_mismatches']);

        if ($issues === 0) {
            $this->info('El inventario está conciliado: ubicaciones, lotes y resúmenes coinciden con los movimientos.');

            return self::SUCCESS;
        }

        Log::warning('Inventario descuadrado', [
            'discrepancies'      => $report['discrepancies'],
            'summary_mismatches' => $report['summary_mismatches'],
        ]);

        $this->warn("Se encontraron {$issues} diferencia(s). Ejecute con --fix para corregirlas.");

        return self::FAILURE;
    }

    /** @param list<array<string, mixed>> $discrepancies */
    private function renderDiscrepancies(array $discrepancies): void
    {
        $this->line('<comment>Lote por almacén: libro de movimientos vs ubicaciones</comment> ('.count($discrepancies).')');

        if ($discrepancies !== []) {
            $this->table(
                ['Almacén', 'Lote ID', 'Lote', 'Variante', 'Según movimientos', 'En ubicaciones'],
                array_map(fn ($d) => [$d['warehouse_id'], $d['batch_id'], $d['lot_number'], $d['product_variant_id'], $d['ledger'], $d['physical']], $discrepancies),
            );
        }
    }

    /** @param list<array<string, mixed>> $mismatches */
    private function renderSummaryMismatches(array $mismatches): void
    {
        $this->line('<comment>Resumen de stock vs stock vigente en ubicaciones</comment> ('.count($mismatches).')');

        if ($mismatches !== []) {
            $this->table(
                ['Almacén', 'Variante', 'Resumen', 'Esperado'],
                array_map(fn ($m) => [$m['warehouse_id'], $m['product_variant_id'], $m['summary'], $m['expected']], $mismatches),
            );
        }
    }

    /** @param list<array<string, mixed>> $movements */
    private function renderForeignLocationMovements(array $movements): void
    {
        $this->line('<comment>Movimientos históricos con ubicación de otro almacén (solo informativo)</comment> ('.count($movements).')');

        if ($movements !== []) {
            $this->table(
                ['Movimiento', 'Tipo', 'Almacén del movimiento', 'Ubicación', 'Almacén de la ubicación'],
                array_map(fn ($m) => array_values($m), $movements),
            );
        }
    }

    /** @param list<array<string, mixed>> $unresolved */
    private function renderFixResult(array $unresolved): int
    {
        if ($unresolved === []) {
            $this->info('Corrección aplicada: ubicaciones, lotes y resúmenes quedaron conciliados con los movimientos.');

            return self::SUCCESS;
        }

        $this->error(count($unresolved).' diferencia(s) no se pudieron corregir automáticamente:');
        $this->table(
            ['Almacén', 'Lote ID', 'Motivo'],
            array_map(fn ($u) => [$u['warehouse_id'], $u['batch_id'], $u['error']], $unresolved),
        );

        return self::FAILURE;
    }
}
