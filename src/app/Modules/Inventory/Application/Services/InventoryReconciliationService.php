<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verifica y corrige la coherencia del inventario por almacén.
 *
 * La fuente de verdad es el libro de movimientos confirmados (`stock_movements`):
 * entradas, salidas, traslados, ajustes, devoluciones y bajas. A partir de él se
 * obtiene cuánto debe haber de cada lote en cada almacén y se compara con:
 *  - `batch_location`: stock físico por ubicación (agrupado por almacén).
 *  - `stock_summaries`: resumen por variante y almacén (lotes vigentes).
 *
 * La corrección nunca modifica movimientos ni los datos registrados en las
 * entradas; solo reubica cantidades en `batch_location` dentro del almacén
 * que corresponde, re-sincroniza los lotes (total y estado, incluido `expired`)
 * y recalcula los resúmenes.
 */
class InventoryReconciliationService
{
    private const TOLERANCE = 0.0005;

    public function __construct(
        private readonly BatchLocationService $batchLocationService,
        private readonly StockCalculator $stockCalculator,
    ) {}

    /**
     * @return array{
     *     discrepancies: list<array{batch_id: int, lot_number: string, product_variant_id: int, warehouse_id: int, ledger: float, physical: float}>,
     *     summary_mismatches: list<array{product_variant_id: int, warehouse_id: int, summary: float, expected: float}>,
     *     foreign_location_movements: list<array{movement_id: int, movement_type: string, warehouse_id: int, location_id: int, location_warehouse_id: int}>,
     *     unresolved: list<array<string, mixed>>,
     *     applied: bool
     * }
     */
    public function run(bool $apply = false): array
    {
        $report = [
            'discrepancies'              => $this->findDiscrepancies(),
            'summary_mismatches'         => $this->findSummaryMismatches(),
            'foreign_location_movements' => $this->findForeignLocationMovements(),
            'unresolved'                 => [],
            'applied'                    => $apply,
        ];

        if (! $apply) {
            return $report;
        }

        DB::transaction(function () use (&$report) {
            foreach ($report['discrepancies'] as $discrepancy) {
                $error = $this->correct($discrepancy);

                if ($error !== null) {
                    $report['unresolved'][] = $discrepancy + ['error' => $error];
                }
            }

            BatchModel::query()->pluck('id')->each(fn (int $batchId) => $this->batchLocationService->syncBatch($batchId));

            $this->summaryPairs()->each(fn (array $pair) => $this->stockCalculator->recalculateSummary(
                $pair['product_variant_id'],
                $pair['warehouse_id'],
            ));
        });

        return $report;
    }

    /**
     * Lotes cuyo stock físico en un almacén difiere de lo que indica el libro
     * de movimientos para ese almacén.
     *
     * @return list<array{batch_id: int, lot_number: string, product_variant_id: int, warehouse_id: int, ledger: float, physical: float}>
     */
    public function findDiscrepancies(): array
    {
        $ledger   = $this->ledgerByBatchAndWarehouse();
        $physical = $this->physicalByBatchAndWarehouse();
        $batches  = BatchModel::query()->get(['id', 'lot_number', 'product_variant_id'])->keyBy('id');

        $discrepancies = [];

        foreach (array_unique([...array_keys($ledger), ...array_keys($physical)]) as $key) {
            $expected = round($ledger[$key] ?? 0.0, 3);
            $actual   = round($physical[$key] ?? 0.0, 3);

            if (abs($expected - $actual) < self::TOLERANCE) {
                continue;
            }

            [$batchId, $warehouseId] = array_map('intval', explode(':', (string) $key));
            $batch = $batches->get($batchId);

            $discrepancies[] = [
                'batch_id'           => $batchId,
                'lot_number'         => (string) $batch?->lot_number,
                'product_variant_id' => (int) $batch?->product_variant_id,
                'warehouse_id'       => $warehouseId,
                'ledger'             => $expected,
                'physical'           => $actual,
            ];
        }

        usort($discrepancies, fn ($a, $b) => [$a['warehouse_id'], $a['batch_id']] <=> [$b['warehouse_id'], $b['batch_id']]);

        return $discrepancies;
    }

    /**
     * Resúmenes de stock que no coinciden con el stock vigente (lotes activos y
     * no vencidos) de las ubicaciones del almacén.
     *
     * @return list<array{product_variant_id: int, warehouse_id: int, summary: float, expected: float}>
     */
    public function findSummaryMismatches(): array
    {
        $expected = DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batches.status', 'active')
            ->whereDate('batches.expiration_date', '>=', Carbon::today())
            ->groupBy('batches.product_variant_id', 'zones.warehouse_id')
            ->get(['batches.product_variant_id', 'zones.warehouse_id', DB::raw('SUM(batch_location.quantity) as quantity')])
            ->mapWithKeys(fn ($row) => ["{$row->product_variant_id}:{$row->warehouse_id}" => (float) $row->quantity]);

        $summaries = StockSummaryModel::query()
            ->get(['product_variant_id', 'warehouse_id', 'total_quantity'])
            ->mapWithKeys(fn ($row) => ["{$row->product_variant_id}:{$row->warehouse_id}" => (float) $row->total_quantity]);

        $mismatches = [];

        foreach ($expected->keys()->merge($summaries->keys())->unique() as $key) {
            $summary = round($summaries->get($key, 0.0), 3);
            $actual  = round($expected->get($key, 0.0), 3);

            if (abs($summary - $actual) < self::TOLERANCE) {
                continue;
            }

            [$variantId, $warehouseId] = array_map('intval', explode(':', (string) $key));

            $mismatches[] = [
                'product_variant_id' => $variantId,
                'warehouse_id'       => $warehouseId,
                'summary'            => $summary,
                'expected'           => $actual,
            ];
        }

        return $mismatches;
    }

    /**
     * Movimientos históricos cuya ubicación pertenece a un almacén distinto al
     * del movimiento. Se informan para auditoría; los movimientos no se modifican.
     *
     * @return list<array{movement_id: int, movement_type: string, warehouse_id: int, location_id: int, location_warehouse_id: int}>
     */
    public function findForeignLocationMovements(): array
    {
        $from = DB::table('stock_movements as m')
            ->join('locations as l', 'l.id', '=', 'm.location_from_id')
            ->join('zones as z', 'z.id', '=', 'l.zone_id')
            ->whereColumn('z.warehouse_id', '!=', 'm.warehouse_id')
            ->select('m.id as movement_id', 'm.movement_type', 'm.warehouse_id', 'm.location_from_id as location_id', 'z.warehouse_id as location_warehouse_id');

        $to = DB::table('stock_movements as m')
            ->join('locations as l', 'l.id', '=', 'm.location_to_id')
            ->join('zones as z', 'z.id', '=', 'l.zone_id')
            ->whereRaw('z.warehouse_id <> COALESCE(m.warehouse_to_id, m.warehouse_id)')
            ->select('m.id as movement_id', 'm.movement_type', DB::raw('COALESCE(m.warehouse_to_id, m.warehouse_id) as warehouse_id'), 'm.location_to_id as location_id', 'z.warehouse_id as location_warehouse_id');

        return $from->unionAll($to)
            ->orderBy('movement_id')
            ->get()
            ->map(fn ($row) => [
                'movement_id'           => (int) $row->movement_id,
                'movement_type'         => (string) $row->movement_type,
                'warehouse_id'          => (int) $row->warehouse_id,
                'location_id'           => (int) $row->location_id,
                'location_warehouse_id' => (int) $row->location_warehouse_id,
            ])
            ->all();
    }

    /**
     * Ajusta el stock físico del lote en el almacén al valor del libro.
     * El faltante se repone en una ubicación del propio almacén; el sobrante
     * se retira de las ubicaciones del propio almacén.
     *
     * @param  array{batch_id: int, warehouse_id: int, ledger: float, physical: float}  $discrepancy
     * @return string|null Motivo por el que no se pudo corregir, o null si se corrigió.
     */
    private function correct(array $discrepancy): ?string
    {
        if ($discrepancy['ledger'] < 0) {
            return 'El libro de movimientos arroja un saldo negativo; requiere revisión manual.';
        }

        $delta = round($discrepancy['ledger'] - $discrepancy['physical'], 3);

        if ($delta < 0) {
            $this->batchLocationService->withdraw($discrepancy['batch_id'], $discrepancy['warehouse_id'], abs($delta));

            return null;
        }

        $locationId = $this->restockLocation($discrepancy['batch_id'], $discrepancy['warehouse_id']);

        if ($locationId === null) {
            return 'No se encontró una ubicación del almacén para reponer el lote.';
        }

        $this->batchLocationService->deposit($discrepancy['batch_id'], $discrepancy['warehouse_id'], $locationId, $delta);

        return null;
    }

    /**
     * Ubicación del almacén donde reponer un faltante del lote: la ubicación
     * donde el lote ya está registrado en ese almacén (la de mayor cantidad) o,
     * en su defecto, la última ubicación del almacén usada por sus movimientos.
     */
    private function restockLocation(int $batchId, int $warehouseId): ?int
    {
        $registered = DB::table('batch_location')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batch_location.batch_id', $batchId)
            ->where('zones.warehouse_id', $warehouseId)
            ->orderByDesc('batch_location.quantity')
            ->orderByDesc('batch_location.updated_at')
            ->value('batch_location.location_id');

        if ($registered !== null) {
            return (int) $registered;
        }

        $fromMovements = DB::table('stock_movements as m')
            ->join('locations as l', function ($join) {
                $join->on('l.id', '=', 'm.location_to_id')->orOn('l.id', '=', 'm.location_from_id');
            })
            ->join('zones as z', 'z.id', '=', 'l.zone_id')
            ->where('m.batch_id', $batchId)
            ->where('z.warehouse_id', $warehouseId)
            ->orderByDesc('m.id')
            ->value('l.id');

        return $fromMovements === null ? null : (int) $fromMovements;
    }

    /**
     * Saldo esperado de cada lote en cada almacén según los movimientos confirmados.
     * Los traslados restan en el almacén de origen y suman en el de destino.
     *
     * @return array<string, float> Clave "batch_id:warehouse_id".
     */
    private function ledgerByBatchAndWarehouse(): array
    {
        $outgoing = DB::table('stock_movements')
            ->where('status', 'confirmed')
            ->whereNotNull('batch_id')
            ->groupBy('batch_id', 'warehouse_id')
            ->select(
                'batch_id',
                'warehouse_id',
                DB::raw("SUM(CASE WHEN movement_type = 'transfer' THEN -quantity ELSE quantity END) as quantity"),
            );

        $incoming = DB::table('stock_movements')
            ->where('status', 'confirmed')
            ->where('movement_type', 'transfer')
            ->whereNotNull('batch_id')
            ->groupBy('batch_id', DB::raw('COALESCE(warehouse_to_id, warehouse_id)'))
            ->select(
                'batch_id',
                DB::raw('COALESCE(warehouse_to_id, warehouse_id) as warehouse_id'),
                DB::raw('SUM(quantity) as quantity'),
            );

        return $this->sumByKey($outgoing->get()->merge($incoming->get()));
    }

    /** @return array<string, float> Clave "batch_id:warehouse_id". */
    private function physicalByBatchAndWarehouse(): array
    {
        $rows = DB::table('batch_location')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->groupBy('batch_location.batch_id', 'zones.warehouse_id')
            ->get([
                'batch_location.batch_id',
                'zones.warehouse_id',
                DB::raw('SUM(batch_location.quantity) as quantity'),
            ]);

        return $this->sumByKey($rows);
    }

    /** @return array<string, float> */
    private function sumByKey(Collection $rows): array
    {
        $totals = [];

        foreach ($rows as $row) {
            $key          = "{$row->batch_id}:{$row->warehouse_id}";
            $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row->quantity;
        }

        return $totals;
    }

    /**
     * Pares variante/almacén con resumen existente o con stock en ubicaciones.
     *
     * @return Collection<int, array{product_variant_id: int, warehouse_id: int}>
     */
    private function summaryPairs(): Collection
    {
        $withStock = DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->distinct()
            ->get(['batches.product_variant_id', 'zones.warehouse_id']);

        $existing = StockSummaryModel::query()->get(['product_variant_id', 'warehouse_id']);

        return $withStock->concat($existing)
            ->map(fn ($row) => [
                'product_variant_id' => (int) $row->product_variant_id,
                'warehouse_id'       => (int) $row->warehouse_id,
            ])
            ->unique(fn (array $pair) => "{$pair['product_variant_id']}:{$pair['warehouse_id']}")
            ->values();
    }
}
