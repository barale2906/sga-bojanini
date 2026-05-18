<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que autentica dispositivos IoT mediante un token estático
 * enviado en el header X-IoT-Token.
 *
 * El token se configura en config/sga.php bajo 'iot.token'.
 * Esto es para el endpoint de lecturas masivas (/sensors/readings/bulk)
 * donde los dispositivos NO tienen un usuario/contraseña, solo un token.
 *
 * Uso en rutas:
 *   Route::post('/sensors/readings/bulk', ...)->middleware('iot.token');
 */
class IoTTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Leer el token del header de la petición
        $providedToken = $request->header('X-IoT-Token');

        // Leer el token esperado desde la configuración
        $expectedToken = config('sga.iot.token');

        // Verificar que el token fue enviado
        if (empty($providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Token IoT requerido. Envíe el header X-IoT-Token.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verificar que el token es correcto
        // Se usa hash_equals() para prevenir timing attacks
        if (!hash_equals((string) $expectedToken, (string) $providedToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Token IoT inválido.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
