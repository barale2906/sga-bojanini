<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Auth\Application\DTOs\SignerInput;
use App\Modules\Auth\Application\Services\SignerSnapshotResolver;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementSignatureModel;
use Illuminate\Support\Facades\DB;

class ConfirmMovementUseCase
{
    public function __construct(
        private readonly RegisterExitUseCase $exitUseCase,
        private readonly TransferStockUseCase $transferUseCase,
        private readonly AdjustStockUseCase $adjustUseCase,
        private readonly RegisterReturnUseCase $returnUseCase,
        private readonly WriteOffExpiredUseCase $writeOffUseCase,
        private readonly RegisterLossUseCase $lossUseCase,
        private readonly SignerSnapshotResolver $signerResolver,
    ) {}

    /**
     * Aplica el stock de un comprobante pendiente y registra sus firmas.
     *
     * Las firmas registradas se resuelven ANTES de abrir la transacción: si el
     * usuario no tiene firma o el PIN es inválido no se toca el inventario.
     * La firma se copia al comprobante (snapshot), así el documento no cambia
     * aunque el usuario actualice su firma después.
     *
     * @param array{delivered_by: SignerInput, received_by: SignerInput} $signers
     * @param int $actingUserId usuario autenticado que confirma (dueño de la firma "stored")
     *
     * @throws \DomainException si el documento ya fue confirmado o entrega y recibe la misma persona
     * @throws \App\Modules\Auth\Domain\Exceptions\SignatureNotRegisteredException
     * @throws \App\Modules\Auth\Domain\Exceptions\InvalidSignaturePinException
     */
    public function execute(int $documentId, array $signers, int $actingUserId): MovementDocumentModel
    {
        $document = MovementDocumentModel::with('movements')->findOrFail($documentId);

        if ($document->status !== MovementStatus::PENDING_SIGNATURE) {
            throw new \DomainException('El documento ya fue confirmado.');
        }

        $rows = $this->signerResolver->resolveAll($signers, $actingUserId);

        DB::transaction(function () use ($document, $rows) {
            foreach ($document->movements as $movement) {
                $this->applyStockByType($movement);
                $movement->status = MovementStatus::CONFIRMED;
                $movement->save();
            }

            foreach ($rows as $role => $row) {
                MovementSignatureModel::create([
                    'movement_document_id' => $document->id,
                    'role'                 => $role,
                    ...$row,
                ]);
            }

            $document->status = MovementStatus::CONFIRMED;
            $document->save();
        });

        return $document->load(['movements.variant.genericProduct', 'movements.batch', 'warehouse', 'warehouseTo', 'user', 'signatures']);
    }

    private function applyStockByType(\App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel $movement): void
    {
        match ($movement->movement_type->value ?? $movement->movement_type) {
            'exit'                 => $this->exitUseCase->applyStock($movement),
            'transfer'             => $this->transferUseCase->applyStock($movement),
            'adjustment'           => $this->adjustUseCase->applyStock($movement),
            'return'               => $this->returnUseCase->applyStock($movement),
            'expiration_write_off' => $this->writeOffUseCase->applyStock($movement),
            'loss'                 => $this->lossUseCase->applyStock($movement),
            default                => throw new \DomainException("Tipo '{$movement->movement_type}' no soporta confirmación por firma."),
        };
    }
}
