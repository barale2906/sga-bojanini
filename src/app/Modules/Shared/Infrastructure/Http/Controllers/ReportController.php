<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Controllers;

use App\Modules\Shared\Application\Services\ReportRequestHandler;
use App\Modules\Shared\Infrastructure\Http\Requests\GenerateReportRequest;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    use ChecksSensorAccess;

    public function __construct(
        private readonly ReportRequestHandler $handler,
    ) {}

    public function inventory(GenerateReportRequest $request): Response
    {
        return $this->handler->handle(Auth::user(), 'inventory', $request->validated());
    }

    public function movements(GenerateReportRequest $request): Response
    {
        return $this->handler->handle(Auth::user(), 'movements', $request->validated());
    }

    public function expiring(GenerateReportRequest $request): Response
    {
        return $this->handler->handle(Auth::user(), 'expiring', $request->validated());
    }

    public function purchases(GenerateReportRequest $request): Response
    {
        return $this->handler->handle(Auth::user(), 'purchases', $request->validated());
    }

    public function consumption(GenerateReportRequest $request): Response
    {
        return $this->handler->handle(Auth::user(), 'consumption', $request->validated());
    }

    public function conditions(GenerateReportRequest $request): Response
    {
        $user = Auth::user();
        $filters = $request->validated();
        $sensorId = $filters['sensor_id'] ?? null;

        if ($sensorId !== null) {
            // PDF: un solo equipo, validar que el usuario tenga acceso a él.
            $this->assertSensorAccess($user, (int) $sensorId);
        } else {
            // Excel/CSV "todos los sensores": restringir a los permitidos.
            $allowedIds = $this->allowedSensorIds($user);
            if ($allowedIds !== null) {
                $filters['sensor_ids'] = $allowedIds;
            }
        }

        return $this->handler->handle($user, 'conditions', $filters);
    }

    public function audit(GenerateReportRequest $request): Response
    {
        return $this->handler->handle(Auth::user(), 'audit', $request->validated());
    }
}
