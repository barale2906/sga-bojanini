<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Application\Services\SignaturePinService;
use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Auth\Domain\Exceptions\SignatureNotRegisteredException;
use App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Genera un PIN nuevo para la firma activa del usuario autenticado (por
 * olvido o sospecha de que alguien lo conoce). El PIN anterior deja de
 * funcionar de inmediato y queda en cuarentena SignaturePinService::QUARANTINE_DAYS
 * días antes de poder asignarse a otro usuario.
 */
class RegenerateSignaturePinUseCase
{
    public function __construct(
        private readonly UserSignatureRepositoryInterface $repository,
        private readonly SignaturePinService $pinService,
    ) {}

    /**
     * @return array{signature: UserSignature, pin: string}
     *
     * @throws SignatureNotRegisteredException si el usuario aún no registra su firma
     */
    public function execute(int $authenticatedUserId): array
    {
        if ($this->repository->findActiveByUser($authenticatedUserId) === null) {
            throw new SignatureNotRegisteredException('Primero debe registrar su firma.');
        }

        return DB::transaction(function () use ($authenticatedUserId) {
            ['pin' => $pin, 'lookup' => $lookup] = $this->pinService->generateUnique();

            $signature = $this->repository->replacePinLookup(
                $authenticatedUserId,
                $lookup,
                now()->addDays(SignaturePinService::QUARANTINE_DAYS),
            );

            return ['signature' => $signature, 'pin' => $pin];
        });
    }
}
