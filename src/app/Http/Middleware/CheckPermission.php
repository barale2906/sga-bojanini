<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermissionTo($permission)) {
            return response()->json([
                'success' => false,
                'message' => "No tienes el permiso '{$permission}' para realizar esta acción.",
            ], 403);
        }

        return $next($request);
    }
}
