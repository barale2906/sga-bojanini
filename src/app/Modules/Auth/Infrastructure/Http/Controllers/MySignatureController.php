<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Application\UseCases\GetMySignatureUseCase;
use App\Modules\Auth\Application\UseCases\RegenerateSignaturePinUseCase;
use App\Modules\Auth\Application\UseCases\SaveMySignatureUseCase;
use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use App\Modules\Auth\Infrastructure\Http\Requests\RegenerateSignaturePinRequest;
use App\Modules\Auth\Infrastructure\Http\Requests\SaveMySignatureRequest;
use App\Modules\Auth\Infrastructure\Http\Resources\MySignatureResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * "Mi firma": firma registrada del usuario autenticado.
 *
 * REGLA DE PRIVACIDAD: ninguna ruta recibe un ID de usuario. Todas operan
 * exclusivamente sobre `$request->user()`, por lo que un usuario no puede
 * ver ni modificar la firma de otro, sin importar su rol o permisos.
 */
class MySignatureController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/auth/me/signature — Firma registrada del usuario autenticado.
     *
     * `data.signature` es null si aún no la ha registrado.
     *
     * @response 200 Firma (o null) y catálogo de tipos de documento
     * @response 401 No autenticado
     */
    public function show(Request $request, GetMySignatureUseCase $useCase): JsonResponse
    {
        $signature = $useCase->execute((int) $request->user()->id);

        return $this->success([
            'signature'      => $signature ? new MySignatureResource($signature) : null,
            'document_types' => array_map(
                fn (IdentityDocumentType $type) => ['value' => $type->value, 'label' => $type->label()],
                IdentityDocumentType::cases(),
            ),
        ], 'Mi firma');
    }

    /**
     * PUT /api/v1/auth/me/signature — Registrar o reemplazar la firma.
     *
     * En el primer registro la respuesta incluye `data.pin` (único momento
     * en que se muestra). En actualizaciones se conserva el PIN vigente y
     * `data.pin` es null.
     *
     * @response 200 Firma actualizada
     * @response 201 Firma registrada por primera vez (incluye PIN)
     * @response 422 Validación fallida (contraseña, documento o imagen)
     * @response 429 Demasiadas peticiones
     */
    public function save(SaveMySignatureRequest $request, SaveMySignatureUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute((int) $request->user()->id, $request->toData());

        $payload = [
            'signature' => new MySignatureResource($result['signature']),
            'pin'       => $result['pin'],
        ];

        return $result['pin'] !== null
            ? $this->created($payload, 'Firma registrada. Guarde su PIN: no se volverá a mostrar.')
            : $this->success($payload, 'Firma actualizada exitosamente');
    }

    /**
     * POST /api/v1/auth/me/signature/pin — Generar un PIN nuevo.
     *
     * El PIN anterior deja de funcionar de inmediato.
     *
     * @response 200 PIN nuevo (único momento en que se muestra)
     * @response 409 El usuario aún no registra su firma
     * @response 422 Contraseña incorrecta
     * @response 429 Demasiadas peticiones
     */
    public function regeneratePin(RegenerateSignaturePinRequest $request, RegenerateSignaturePinUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute((int) $request->user()->id);

        return $this->success([
            'signature' => new MySignatureResource($result['signature']),
            'pin'       => $result['pin'],
        ], 'PIN regenerado. Guárdelo: no se volverá a mostrar.');
    }
}
