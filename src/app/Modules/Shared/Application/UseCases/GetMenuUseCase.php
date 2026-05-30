<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\UseCases;

use App\Modules\Shared\Application\Services\MenuBuilder;

/**
 * Construye el árbol de navegación para el usuario autenticado.
 *
 * Recibe la lista de permisos del usuario y delega en MenuBuilder
 * la resolución de qué secciones e ítems son visibles y qué acciones
 * están habilitadas.
 */
class GetMenuUseCase
{
    public function __construct(
        private readonly MenuBuilder $menuBuilder,
    ) {}

    /**
     * @param  string[]  $userPermissions
     * @return array<int, array<string, mixed>>
     */
    public function execute(array $userPermissions): array
    {
        return $this->menuBuilder->build($userPermissions);
    }
}
