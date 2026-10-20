<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSignatureModel;
use App\Modules\Auth\Application\DTOs\SignerInput;
use App\Modules\Auth\Application\Services\SignerSnapshotResolver;

/**
 * Firma documentos de activos (traslados y mantenimientos) con la misma
 * metodología de Inventario: quien entrega firma con su sesión y quien
 * recibe con PIN o en pad.
 *
 * El flujo es en dos pasos para no tocar datos si la firma falla:
 * `resolve()` ANTES de la transacción y `store()` dentro de ella.
 */
class AssetSignatureRecorder
{
    public function __construct(
        private readonly SignerSnapshotResolver $resolver,
    ) {}

    /**
     * @param array{delivered_by: SignerInput, received_by: SignerInput} $signers
     *
     * @return array<string, array<string, mixed>>
     */
    public function resolve(array $signers, int $actingUserId): array
    {
        return $this->resolver->resolveAll($signers, $actingUserId);
    }

    /** @param array<string, array<string, mixed>> $rows salida de resolve() */
    public function store(string $signableType, int $signableId, array $rows): void
    {
        foreach ($rows as $role => $row) {
            AssetSignatureModel::create([
                'signable_type' => $signableType,
                'signable_id'   => $signableId,
                'role'          => $role,
                ...$row,
            ]);
        }
    }
}
