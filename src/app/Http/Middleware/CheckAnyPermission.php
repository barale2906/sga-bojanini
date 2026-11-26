<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza cuando el usuario tiene AL MENOS UNO de los permisos indicados
 * (`permission.any:a,b,c`). Complementa a CheckPermission, que exige uno solo.
 */
class CheckAnyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user) {
            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission)) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'No tienes ninguno de los permisos requeridos ('.implode(', ', $permissions).') para realizar esta acción.',
        ], 403);
    }
}
