<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Controllers;

use App\Modules\Integration\Application\UseCases\AnalyzeDemandUseCase;
use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SchedulingController extends Controller
{
    use ApiResponse;

    public function appointments(Request $request, SchedulingServiceInterface $schedulingService): JsonResponse
    {
        $from = Carbon::parse($request->query('date_from', now()->toDateString()));
        $to   = Carbon::parse($request->query('date_to', now()->addDays(7)->toDateString()));

        $appointments = $schedulingService->getUpcomingAppointments($from, $to);

        return $this->success($appointments, 'Citas obtenidas del sistema de agendamiento');
    }

    public function demandAnalysis(Request $request, AnalyzeDemandUseCase $useCase): JsonResponse
    {
        $daysAhead = (int) $request->query('days_ahead', 7);
        $result    = $useCase->execute($daysAhead);

        return $this->success($result, 'Análisis de demanda completado');
    }
}
