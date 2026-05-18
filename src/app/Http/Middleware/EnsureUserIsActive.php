<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.',
            ], 403);
        }

        return $next($request);
    }
}
