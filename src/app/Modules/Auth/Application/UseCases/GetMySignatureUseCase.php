<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface;

/**
 * Obtiene la firma activa del usuario autenticado.
 *
 * Recibe siempre el ID del usuario de la sesión: no existe un caso de uso
 * para consultar la firma de otro usuario, sin importar su rol.
 */
class GetMySignatureUseCase
{
    public function __construct(
        private readonly UserSignatureRepositoryInterface $repository,
    ) {}

    public function execute(int $authenticatedUserId): ?UserSignature
    {
        return $this->repository->findActiveByUser($authenticatedUserId);
    }
}
