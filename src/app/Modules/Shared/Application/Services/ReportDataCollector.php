<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReportDataCollector
{
    /** Filas máximas que entrega el reporte de auditoría, sin importar cuántas existan. */
    private const AUDIT_ROW_LIMIT = 500;

    public function collect(string $reportType, array $filters): array
    {
        return match ($reportType) {
            'inventory'   => $this->inventory($filters),
            'movements'   => $this->movements($filters),
            'expiring'    => $this->expiring($filters),
            'purchases'   => $this->purchases($filters),
            'consumption' => $this->consumption($filters),
            'audit'       => $this->audit($filters),
            'conditions'  => $this->conditions($filters),
            default       => throw new \InvalidArgumentException("Tipo de reporte no soportado: {$reportType}"),
        };
    }

    /**
     * Estima cuántas filas tendrá el reporte sin materializarlas, para que
     * el llamador decida si conviene generarlo de forma síncrona o en
     * background. No necesita ser exacto, solo representativo.
     */
    public function estimateCount(string $reportType, array $filters): int
    {
        return match ($reportType) {
            'inventory'   => $this->inventoryQuery($filters)->count(),
            'movements'   => $this->movementsCount($filters),
            'expiring'    => $this->expiringQuery($filters)->count(),
            'purchases'   => $this->purchasesQuery($filters)->count(),
            'consumption' => $this->consumptionCount($filters),
            'audit'       => min($this->auditQuery($filters)->count(), self::AUDIT_ROW_LIMIT),
            'conditions'  => $this->conditionsCount($filters),
            default       => throw new \InvalidArgumentException("Tipo de reporte no soportado: {$reportType}"),
        };
    }

    private function inventoryQuery(array $filters): Builder
    {
        $query = StockSummaryModel::with(['product.category', 'warehouse']);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $filters['category_id']));
        }

        return $query;
    }

    private function inventory(array $filters): array
    {
        $rows = $this->inventoryQuery($filters)->get();
        $products = [];
        $stockOk = $stockLow = $stockCritical = 0;

        foreach ($rows as $row) {
            $product = $row->product;
            $stock = (int) $row->available_quantity;
            $min = (int) ($product->min_stock ?? 0);
            $reorder = (int) ($product->reorder_point ?? 0);

            if ($stock <= $min) {
                $status = 'Crítico';
                $class = 'stock-critical';
                $stockCritical++;
            } elseif ($stock <= $reorder) {
                $status = 'Bajo';
                $class = 'stock-low';
                $stockLow++;
            } else {
                $status = 'OK';
                $class = 'stock-ok';
                $stockOk++;
            }

            $products[] = [
                'code'          => $product->code,
                'name'          => $product->name,
                'category'      => $product->category?->name ?? '-',
                'location'      => $row->warehouse?->name ?? '-',
                'current_stock' => $stock,
                'min_stock'     => $min,
                'max_stock'     => (int) ($product->max_stock ?? 0),
                'stock_status'  => $status,
                'stock_class'   => $class,
                'stock_value'   => 0,
            ];
        }

        return [
            'generated'          => now()->format('Y-m-d H:i:s'),
            'generatedBy'        => Auth::user()?->name ?? 'Sistema',
            'warehouseName'      => $filters['warehouse_name'] ?? 'Todos',
            'totalProducts'      => count($products),
            'totalStockValue'    => 0,
            'stockOkCount'       => $stockOk,
            'stockLowCount'      => $stockLow,
            'stockCriticalCount' => $stockCritical,
            'products'           => $products,
            'rows'               => $products,
            'headers'            => ['Código', 'Producto', 'Categoría', 'Almacén', 'Stock', 'Mín', 'Máx', 'Estado'],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parsedRange(array $filters, int $defaultDays = 30): array
    {
        $from = Carbon::parse($filters['date_from'] ?? now()->subDays($defaultDays)->toDateString())->startOfDay();
        $to   = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        return [$from, $to];
    }

    private function movementsQuery(array $filters, Carbon $from, Carbon $to): Builder
    {
        $query = StockMovementModel::with(['product', 'warehouse', 'user'])
            ->whereBetween('created_at', [$from, $to]);

        if (!empty($filters['type'])) {
            $query->where('movement_type', $filters['type']);
        }

        return $query;
    }

    private function movementsCount(array $filters): int
    {
        [$from, $to] = $this->parsedRange($filters);

        return $this->movementsQuery($filters, $from, $to)->count();
    }

    private function movements(array $filters): array
    {
        [$from, $to] = $this->parsedRange($filters);

        $rows = $this->movementsQuery($filters, $from, $to)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => [
                'date'      => $m->created_at->format('Y-m-d H:i'),
                'type'      => $m->movement_type,
                'product'   => $m->product?->name ?? '-',
                'warehouse' => $m->warehouse?->name ?? '-',
                'quantity'  => $m->quantity,
                'user'      => $m->user?->name ?? '-',
            ])->all();

        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'rows'      => $rows,
            'headers'   => ['Fecha', 'Tipo', 'Producto', 'Almacén', 'Cantidad', 'Usuario'],
        ];
    }

    private function expiringQuery(array $filters): Builder
    {
        $days = (int) ($filters['days'] ?? 30);
        $limit = Carbon::today()->addDays($days);

        $query = BatchModel::with('product')
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiration_date', '<=', $limit);

        if (!empty($filters['warehouse_id'])) {
            // Un lote no tiene warehouse_id propio: se ubica en una o varias
            // locations (batch_location), y cada location pertenece a una
            // zona, que a su vez pertenece a un almacén.
            $query->whereHas(
                'locations.zone',
                fn ($q) => $q->where('warehouse_id', $filters['warehouse_id']),
            );
        }

        return $query;
    }

    private function expiring(array $filters): array
    {
        $days = (int) ($filters['days'] ?? 30);
        $from = Carbon::today();
        $to   = $from->copy()->addDays($days);

        $rows = $this->expiringQuery($filters)
            ->orderBy('expiration_date')
            ->get()
            ->map(fn ($b) => [
                'lot'        => $b->lot_number,
                'product'    => $b->product?->name ?? '-',
                'expires'    => $b->expiration_date->format('Y-m-d'),
                'quantity'   => $b->quantity_available,
                'days_left'  => (int) now()->diffInDays($b->expiration_date, false),
            ])->all();

        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'days'      => $days,
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'rows'      => $rows,
            'headers'   => ['Lote', 'Producto', 'Vence', 'Cantidad', 'Días restantes'],
        ];
    }

    private function purchasesQuery(array $filters): Builder
    {
        $query = PurchaseOrderModel::with(['supplier', 'warehouse']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }

    private function purchases(array $filters): array
    {
        $rows = $this->purchasesQuery($filters)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'code'     => $o->code,
                'supplier' => $o->supplier?->name ?? '-',
                'status'   => $o->status,
                'total'    => (float) $o->total_amount,
                'date'     => $o->created_at->format('Y-m-d'),
            ])->all();

        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'rows'      => $rows,
            'headers'   => ['Código', 'Proveedor', 'Estado', 'Total', 'Fecha'],
        ];
    }

    private function consumptionQuery(Carbon $from, Carbon $to): Builder
    {
        return ConsumptionRecordModel::with(['product', 'user'])
            ->whereBetween('consumed_at', [$from, $to]);
    }

    private function consumptionCount(array $filters): int
    {
        [$from, $to] = $this->parsedRange($filters);

        return $this->consumptionQuery($from, $to)->count();
    }

    private function consumption(array $filters): array
    {
        [$from, $to] = $this->parsedRange($filters);

        $rows = $this->consumptionQuery($from, $to)
            ->orderByDesc('consumed_at')
            ->get()
            ->map(fn ($r) => [
                'date'     => $r->consumed_at->format('Y-m-d H:i'),
                'patient'  => $r->patient_identifier ?? '-',
                'product'  => $r->product?->name ?? '-',
                'quantity' => $r->quantity,
                'sync'     => $r->sync_status,
                'user'     => $r->user?->name ?? '-',
            ])->all();

        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'rows'      => $rows,
            'headers'   => ['Fecha', 'Paciente', 'Producto', 'Cantidad', 'Sync', 'Usuario'],
        ];
    }

    private function auditQuery(array $filters): Builder
    {
        $query = AuditLogModel::with('user')->orderByDesc('created_at');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }

    private function audit(array $filters): array
    {
        $rows = $this->auditQuery($filters)
            ->limit(self::AUDIT_ROW_LIMIT)
            ->get()
            ->map(fn ($l) => [
                'date'   => $l->created_at->format('Y-m-d H:i'),
                'user'   => $l->user?->name ?? 'Sistema',
                'action' => $l->action,
                'type'   => $l->auditable_type,
                'id'     => $l->auditable_id,
            ])->all();

        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'rows'      => $rows,
            'headers'   => ['Fecha', 'Usuario', 'Acción', 'Entidad', 'ID'],
        ];
    }

    /**
     * Para PDF, "conditions" se delega al caso de uso del módulo de
     * Monitoring (gráfico de control, estadísticas, etc., ver
     * ReportExportService::generateConditionsPdf()). Este método solo
     * alimenta el formato tabular (Excel/CSV) y la estimación de tamaño.
     */
    private function conditionsQuery(array $filters): Builder
    {
        [$from, $to] = $this->parsedRange($filters);

        $query = SensorReadingModel::with(['sensor.zone'])
            ->whereBetween('recorded_at', [$from, $to]);

        if (!empty($filters['sensor_id'])) {
            $query->where('sensor_id', $filters['sensor_id']);
        } elseif (!empty($filters['sensor_ids'])) {
            // Usuario restringido pidiendo "todos": acotar a sus sensores permitidos.
            $query->whereIn('sensor_id', $filters['sensor_ids']);
        }

        return $query;
    }

    private function conditionsCount(array $filters): int
    {
        return $this->conditionsQuery($filters)->count();
    }

    private function conditions(array $filters): array
    {
        [$from, $to] = $this->parsedRange($filters);

        $rows = $this->conditionsQuery($filters)
            ->orderBy('recorded_at')
            ->get()
            ->map(function ($reading) {
                $sensor = $reading->sensor;
                $zone = $sensor?->zone;
                $limits = $this->zoneLimitsFor($sensor, $zone);
                $value = (float) $reading->value;
                $outOfRange = $limits !== null && ($value < $limits[0] || $value > $limits[1]);

                return [
                    'sensor' => $sensor ? "{$sensor->code} — {$sensor->name}" : '-',
                    'zone'   => $zone?->name ?? '-',
                    'date'   => $reading->recorded_at->format('Y-m-d H:i'),
                    'value'  => $value,
                    'unit'   => $sensor?->unit ?? '-',
                    'limits' => $limits !== null ? "{$limits[0]} – {$limits[1]}" : '-',
                    'status' => $outOfRange ? 'Fuera de rango' : 'OK',
                ];
            })->all();

        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'rows'      => $rows,
            'headers'   => ['Sensor', 'Zona', 'Fecha', 'Valor', 'Unidad', 'Límites', 'Estado'],
        ];
    }

    /**
     * @return array{0: float, 1: float}|null [mínimo, máximo] según el tipo
     *         de sensor (temperatura u humedad), o null si no hay zona.
     */
    private function zoneLimitsFor($sensor, $zone): ?array
    {
        if ($sensor === null || $zone === null) {
            return null;
        }

        return $sensor->type === 'temperature'
            ? [(float) ($zone->temp_min ?? 0), (float) ($zone->temp_max ?? 0)]
            : [(float) ($zone->humidity_min ?? 0), (float) ($zone->humidity_max ?? 0)];
    }
}
