<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function inventory(): JsonResponse
    {
        $data = Cache::remember('dashboard:inventory', 300, function () {
            $productStock = DB::table('stock_summaries as ss')
                ->join('product_variants as pv', 'ss.product_variant_id', '=', 'pv.id')
                ->join('product_generics as p', 'pv.generic_product_id', '=', 'p.id')
                ->selectRaw('
                    ss.product_variant_id,
                    SUM(ss.available_quantity) as available_quantity,
                    COALESCE(p.reorder_point, 0) as reorder_point,
                    COALESCE(p.min_stock, 0) as min_stock
                ')
                ->groupBy('ss.product_variant_id', 'p.reorder_point', 'p.min_stock')
                ->get();

            $stockSummary = (object) [
                'total_products' => $productStock->count(),
                'total_units' => $productStock->sum('available_quantity'),
                'stock_ok_count' => $productStock->filter(
                    fn ($p) => $p->available_quantity > $p->reorder_point
                )->count(),
                'stock_low_count' => $productStock->filter(
                    fn ($p) => $p->available_quantity <= $p->reorder_point && $p->available_quantity > $p->min_stock
                )->count(),
                'stock_critical_count' => $productStock->filter(
                    fn ($p) => $p->available_quantity <= $p->min_stock
                )->count(),
            ];

            $expiringToday = DB::table('batches')
                ->where('status', 'active')
                ->whereDate('expiration_date', today())
                ->count();

            $expiring7Days = DB::table('batches')
                ->where('status', 'active')
                ->whereBetween('expiration_date', [today(), today()->addDays(7)])
                ->count();

            $expiring30Days = DB::table('batches')
                ->where('status', 'active')
                ->whereBetween('expiration_date', [today(), today()->addDays(30)])
                ->count();

            $movementsToday = DB::table('stock_movements')
                ->whereDate('created_at', today())
                ->count();

            $topConsumed = DB::table('stock_movements')
                ->join('product_variants as pv', 'stock_movements.product_variant_id', '=', 'pv.id')
                ->join('product_generics as p', 'pv.generic_product_id', '=', 'p.id')
                ->where('stock_movements.movement_type', 'exit')
                ->where('stock_movements.created_at', '>=', now()->subMonth())
                ->selectRaw('p.id, p.name, p.barcode, SUM(ABS(stock_movements.quantity)) as total_consumed')
                ->groupBy('p.id', 'p.name', 'p.barcode')
                ->orderByDesc('total_consumed')
                ->limit(10)
                ->get()
                ->toArray();

            return [
                'total_products'        => (int) ($stockSummary->total_products ?? 0),
                'total_stock_value'     => 0,
                'total_units'           => (int) ($stockSummary->total_units ?? 0),
                'stock_ok_count'        => (int) ($stockSummary->stock_ok_count ?? 0),
                'stock_low_count'       => (int) ($stockSummary->stock_low_count ?? 0),
                'stock_critical_count'  => (int) ($stockSummary->stock_critical_count ?? 0),
                'expiring_today'        => $expiringToday,
                'expiring_7_days'       => $expiring7Days,
                'expiring_30_days'      => $expiring30Days,
                'movements_today'       => $movementsToday,
                'top_consumed_products' => $topConsumed,
            ];
        });

        return $this->success($data);
    }

    public function purchasing(): JsonResponse
    {
        $data = Cache::remember('dashboard:purchasing', 300, function () {
            return [
                'pending_orders'  => DB::table('purchase_orders')->where('status', 'pending_approval')->count(),
                'approved_orders' => DB::table('purchase_orders')->where('status', 'approved')->count(),
                'total_month'     => (float) DB::table('purchase_orders')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('total_amount'),
            ];
        });

        return $this->success($data);
    }

    public function monitoring(): JsonResponse
    {
        $data = Cache::remember('dashboard:monitoring', 300, function () {
            return [
                'active_sensors' => DB::table('sensors')->where('is_active', true)->count(),
                'alerts_today'   => DB::table('notifications')
                    ->whereDate('created_at', today())
                    ->where('data', 'like', '%condition%')
                    ->count(),
                'readings_today' => DB::table('sensor_readings')
                    ->whereDate('created_at', today())
                    ->count(),
            ];
        });

        return $this->success($data);
    }

    public function activity(): JsonResponse
    {
        $data = Cache::remember('dashboard:activity', 300, function () {
            return DB::table('audit_logs')
                ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
                ->select(
                    'audit_logs.action',
                    'audit_logs.auditable_type',
                    'audit_logs.created_at',
                    'users.name as user_name',
                )
                ->orderByDesc('audit_logs.created_at')
                ->limit(20)
                ->get()
                ->toArray();
        });

        return $this->success($data);
    }

    public function alerts(): JsonResponse
    {
        $alerts = Auth::user()
            ->unreadNotifications()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'title'      => $n->data['title'] ?? '',
                'message'    => $n->data['message'] ?? '',
                'severity'   => $n->data['severity'] ?? 'info',
                'created_at' => $n->created_at->format('Y-m-d H:i:s'),
            ]);

        return $this->success($alerts);
    }
}
