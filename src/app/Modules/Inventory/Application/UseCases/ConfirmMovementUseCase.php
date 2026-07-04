<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementSignatureModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
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

    public function execute(int $movementId, array $signatures): StockMovementModel
    {
        $movement = StockMovementModel::findOrFail($movementId);

        if ($movement->status !== MovementStatus::PENDING_SIGNATURE) {
            throw new \DomainException('El movimiento ya fue confirmado.');
        }

        DB::transaction(function () use ($movement, $signatures) {
            $this->applyStockByType($movement);

            foreach ($signatures as $role => $data) {
                MovementSignatureModel::create([
                    'movement_id'     => $movement->id,
                    'role'            => $role,
                    'signer_name'     => $data['name'],
                    'signer_document' => $data['document'],
                    'signature_data'  => $data['signature'],
                ]);
            }

            $movement->status = MovementStatus::CONFIRMED;
            $movement->save();
        });

        return $movement->load(['product', 'batch', 'user', 'warehouse', 'warehouseTo', 'signatures']);
    }

    private function applyStockByType(StockMovementModel $movement): void
    {
        match ($movement->movement_type) {
            'exit'                 => $this->exitUseCase->applyStock($movement),
            'transfer'             => $this->transferUseCase->applyStock($movement),
            'adjustment'           => $this->adjustUseCase->applyStock($movement),
            'return'               => $this->returnUseCase->applyStock($movement),
            'expiration_write_off' => $this->writeOffUseCase->applyStock($movement),
            'loss'                 => $this->lossUseCase->applyStock($movement),
            default                => throw new \DomainException("Tipo de movimiento '{$movement->movement_type}' no soporta confirmación por firma."),
        };
    }
}
