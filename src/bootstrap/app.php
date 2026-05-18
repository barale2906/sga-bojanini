<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. Envía un token válido en el header Authorization.',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para realizar esta acción.',
                ], 403);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Método HTTP no permitido para esta ruta.',
                ], 405);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demasiadas peticiones. Espera un momento antes de intentar de nuevo.',
                ], 429);
            }
        });

        $exceptions->render(function (\DomainException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 409);
            }
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $isDebug = config('app.debug');

                return response()->json([
                    'success'   => false,
                    'message'   => $isDebug ? $e->getMessage() : 'Error interno del servidor.',
                    'exception' => $isDebug ? $e::class : null,
                    'trace'     => $isDebug ? collect($e->getTrace())->take(5)->toArray() : null,
                ], 500);
            }
        });
    })->create();
