<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Middleware;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que registra accesos HTTP a endpoints sensibles.
 */
class AuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() || $response->isRedirection()) {
            try {
                AuditLogModel::create([
                    'user_id'        => Auth::id(),
                    'action'         => 'access',
                    'auditable_type' => 'endpoint',
                    'auditable_id'   => null,
                    'old_values'     => null,
                    'new_values'     => [
                        'method' => $request->method(),
                        'url'    => $request->fullUrl(),
                        'params' => $request->except(['password', 'token', 'secret']),
                    ],
                    'ip_address'     => $request->ip(),
                    'user_agent'     => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Error en AuditMiddleware: '.$e->getMessage());
            }
        }

        return $response;
    }
}
