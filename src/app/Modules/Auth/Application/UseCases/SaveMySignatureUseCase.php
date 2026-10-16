<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Application\DTOs\SaveSignatureData;
use App\Modules\Auth\Application\Services\SignaturePinService;
use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Registra (o reemplaza) la firma del usuario autenticado.
 *
 * - Primer registro: se genera un PIN y se devuelve en claro UNA sola vez.
 * - Actualización: se crea una versión nueva y se conserva el PIN vigente
 *   (no se devuelve; el usuario puede regenerarlo aparte).
 */
class SaveMySignatureUseCase
{
    public function __construct(
        private readonly UserSignatureRepositoryInterface $repository,
        private readonly SignaturePinService $pinService,
    ) {}

    /**
     * @return array{signature: UserSignature, pin: string|null}
     */
    public function execute(int $authenticatedUserId, SaveSignatureData $data): array
    {
        return DB::transaction(function () use ($authenticatedUserId, $data) {
            $pinLookup = $this->repository->findActivePinLookup($authenticatedUserId);
            $plainPin  = null;

            if ($pinLookup === null) {
                ['pin' => $plainPin, 'lookup' => $pinLookup] = $this->pinService->generateUnique();
            }

            $signature = $this->repository->registerVersion(
                userId: $authenticatedUserId,
                documentType: $data->documentType,
                documentNumber: $data->documentNumber,
                signatureData: $data->signatureData,
                pinLookup: $pinLookup,
            );

            return ['signature' => $signature, 'pin' => $plainPin];
        });
    }
}
