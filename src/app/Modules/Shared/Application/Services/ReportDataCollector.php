<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportDataCollector
{
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

    private function inventory(array $filters): array
    {
        $query = StockSummaryModel::with(['product.category', 'warehouse']);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $filters['category_id']));
        }

        $rows = $query->get();
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

    private function movements(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'] ?? now()->subDays(30)->toDateString())->startOfDay();
        $to   = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        $query = StockMovementModel::with(['product', 'warehouse', 'user'])
            ->whereBetween('created_at', [$from, $to]);

        if (!empty($filters['type'])) {
            $query->where('movement_type', $filters['type']);
        }

        $rows = $query->orderByDesc('created_at')->get()->map(fn ($m) => [
            'date'      => $m->created_at->format('Y-m-d H:i'),
            'type'      => $m->movement_type,
            'product'   => $m->product?->name ?? '-',
            'warehouse' => $m->warehouse?->name ?? '-',
            'quantity'  => $m->quantity,
            'user'      => $m->user?->name ?? '-',
        ])->all();

        return [
            'generated'   => now()->format('Y-m-d H:i:s'),
            'date_from'   => $from->toDateString(),
            'date_to'     => $to->toDateString(),
            'rows'        => $rows,
            'headers'     => ['Fecha', 'Tipo', 'Producto', 'Almacén', 'Cantidad', 'Usuario'],
        ];
    }

    private function expiring(array $filters): array
    {
        $days = (int) ($filters['days'] ?? 30);
        $limit = Carbon::today()->addDays($days);

        $rows = BatchModel::with('product')
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiration_date', '<=', $limit)
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
            'rows'      => $rows,
            'headers'   => ['Lote', 'Producto', 'Vence', 'Cantidad', 'Días restantes'],
        ];
    }

    private function purchases(array $filters): array
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

        $rows = $query->orderByDesc('created_at')->get()->map(fn ($o) => [
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

    private function consumption(array $filters): array
    {
        $from = Carbon::parse($filters['date_from'] ?? now()->subDays(30)->toDateString())->startOfDay();
        $to   = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        $rows = ConsumptionRecordModel::with(['product', 'user'])
            ->whereBetween('consumed_at', [$from, $to])
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

    private function audit(array $filters): array
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

        $rows = $query->limit(500)->get()->map(fn ($l) => [
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

    private function conditions(array $filters): array
    {
        return [
            'generated' => now()->format('Y-m-d H:i:s'),
            'message'   => 'Use el endpoint monitoring/reports/generate para PDF detallado de condiciones.',
            'rows'      => [],
            'headers'   => ['Sensor', 'Fecha', 'Valor'],
        ];
    }
}
