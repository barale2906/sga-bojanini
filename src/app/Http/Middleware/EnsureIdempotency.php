<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Shared\Infrastructure\Persistence\Models\IdempotencyKeyModel;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Hace idempotentes las solicitudes de creación que envían `Idempotency-Key`.
 *
 * - Sin la cabecera la solicitud se procesa como siempre.
 * - Con la misma clave, usuario y ruta, y el mismo cuerpo, se devuelve la respuesta
 *   original (cabecera `Idempotent-Replayed: true`) sin volver a ejecutar nada.
 * - Misma clave con otro cuerpo → 422; misma clave aún en curso → 409.
 * - Solo se conservan las respuestas exitosas (2xx): un error se puede reintentar con la misma clave.
 */
class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    private const KEY_PATTERN = '/^[A-Za-z0-9_\-:.]{1,100}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if ($key === null || $key === '') {
            return $next($request);
        }

        if (! preg_match(self::KEY_PATTERN, $key)) {
            return $this->error('La clave de idempotencia no es válida: use de 1 a 100 letras, números, guion, punto o dos puntos.', 422);
        }

        $userId = (int) $request->user()?->getAuthIdentifier();
        $path   = $request->path();
        $hash   = hash('sha256', $request->getContent());

        $record = IdempotencyKeyModel::where(['user_id' => $userId, 'key' => $key, 'path' => $path])->first();

        if ($record === null) {
            try {
                $record = IdempotencyKeyModel::create([
                    'user_id'      => $userId,
                    'key'          => $key,
                    'path'         => $path,
                    'request_hash' => $hash,
                ]);
            } catch (UniqueConstraintViolationException) {
                return $this->inProgress();
            }

            return $this->execute($request, $next, $record);
        }

        return $this->replay($record, $hash);
    }

    private function execute(Request $request, Closure $next, IdempotencyKeyModel $record): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();
            throw $e;
        }

        if ($response->isSuccessful()) {
            $record->update([
                'response_status' => $response->getStatusCode(),
                'response_body'   => (string) $response->getContent(),
            ]);
        } else {
            $record->delete();
        }

        return $response;
    }

    private function replay(IdempotencyKeyModel $record, string $hash): Response
    {
        if (! hash_equals($record->request_hash, $hash)) {
            return $this->error('La clave de idempotencia ya se usó con una solicitud distinta.', 422);
        }

        if ($record->response_status === null) {
            return $this->inProgress();
        }

        return new Response(
            $record->response_body,
            $record->response_status,
            ['Content-Type' => 'application/json', 'Idempotent-Replayed' => 'true'],
        );
    }

    private function inProgress(): JsonResponse
    {
        return $this->error('La solicitud con esta clave sigue en curso. Espere un momento y consulte antes de reintentar.', 409);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
