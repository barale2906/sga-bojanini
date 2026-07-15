<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

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
    ) {}

    public function execute(int $documentId, array $signatures): MovementDocumentModel
    {
        $document = MovementDocumentModel::with('movements')->findOrFail($documentId);

        if ($document->status !== MovementStatus::PENDING_SIGNATURE) {
            throw new \DomainException('El documento ya fue confirmado.');
        }

        DB::transaction(function () use ($document, $signatures) {
            foreach ($document->movements as $movement) {
                $this->applyStockByType($movement);
                $movement->status = MovementStatus::CONFIRMED;
                $movement->save();
            }

            foreach ($signatures as $role => $data) {
                MovementSignatureModel::create([
                    'movement_document_id' => $document->id,
                    'role'                 => $role,
                    'signer_name'          => $data['name'],
                    'signer_document'      => $data['document'],
                    'signature_data'       => $data['signature'],
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
