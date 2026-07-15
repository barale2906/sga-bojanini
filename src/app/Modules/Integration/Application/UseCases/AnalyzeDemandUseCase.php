<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application\UseCases;

use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use App\Modules\Inventory\Domain\Repositories\StockSummaryRepositoryInterface;
use Carbon\Carbon;

class AnalyzeDemandUseCase
{
    public function __construct(
        private readonly SchedulingServiceInterface $schedulingService,
        private readonly StockSummaryRepositoryInterface $stockRepository,
    ) {}

    public function execute(int $daysAhead = 7): array
    {
        $from = Carbon::today();
        $to   = Carbon::today()->addDays($daysAhead);

        $appointments = $this->schedulingService->getUpcomingAppointments($from, $to);

        $activeAppointments = array_filter(
            $appointments,
            fn ($apt) => in_array($apt['status'], ['confirmed', 'pending'], true),
        );

        $serviceProductMap = config('sga.service_product_map', []);

        $demandByProduct = [];

        foreach ($activeAppointments as $apt) {
            $serviceType = $apt['service_type'];

            if (!isset($serviceProductMap[$serviceType])) {
                continue;
            }

            foreach ($serviceProductMap[$serviceType] as $productSpec) {
                $code = $productSpec['product_barcode'];
                if (!isset($demandByProduct[$code])) {
                    $demandByProduct[$code] = [
                        'product_barcode'      => $code,
                        'expected_demand'   => 0,
                        'appointment_count' => 0,
                    ];
                }
                $demandByProduct[$code]['expected_demand'] += $productSpec['quantity_per_service'];
                $demandByProduct[$code]['appointment_count']++;
            }
        }

        $deficitProducts = [];
        $surplusProducts = [];

        foreach ($demandByProduct as &$item) {
            $stock = $this->stockRepository->findByProductCode($item['product_barcode']);

            $currentStock = $stock ? $stock->getCurrentQuantity() : 0;
            $item['current_stock'] = $currentStock;
            $item['balance']       = $currentStock - $item['expected_demand'];

            if ($item['balance'] < 0) {
                $deficitProducts[] = array_merge($item, [
                    'deficit' => abs($item['balance']),
                ]);
            } else {
                $surplusProducts[] = $item;
            }
        }
        unset($item);

        usort($deficitProducts, fn ($a, $b) => $b['deficit'] <=> $a['deficit']);

        return [
            'analysis_period'    => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'total_appointments' => count($activeAppointments),
            'demand_by_product'  => array_values($demandByProduct),
            'deficit_products'   => $deficitProducts,
            'surplus_products'   => $surplusProducts,
        ];
    }
}
