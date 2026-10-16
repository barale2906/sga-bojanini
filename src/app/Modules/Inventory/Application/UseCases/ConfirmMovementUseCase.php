<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Auth\Application\Services\RegisteredSignatureResolver;
use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Inventory\Application\DTOs\SignerInput;
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
        private readonly RegisteredSignatureResolver $signatureResolver,
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

        $rows = [
            'delivered_by' => $this->resolveSigner($signers['delivered_by'], $actingUserId),
            'received_by'  => $this->resolveSigner($signers['received_by'], $actingUserId),
        ];

        if ($rows['delivered_by']['user_id'] !== null
            && $rows['delivered_by']['user_id'] === $rows['received_by']['user_id']) {
            throw new \DomainException('Quien recibe no puede ser la misma persona que entrega.');
        }

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

    /**
     * Convierte la entrada de un rol en los atributos de su fila en movement_signatures.
     *
     * @return array{method: string, user_id: int|null, user_signature_id: int|null, signer_name: string, signer_document: string, signature_data: string}
     */
    private function resolveSigner(SignerInput $input, int $actingUserId): array
    {
        return match ($input->method) {
            SignerInput::METHOD_STORED => $this->fromRegistered(
                SignerInput::METHOD_STORED,
                $this->signatureResolver->forUser($actingUserId),
            ),
            SignerInput::METHOD_PIN => $this->fromRegistered(
                SignerInput::METHOD_PIN,
                $this->signatureResolver->byPin((string) $input->pin, (string) $actingUserId, 'received_by.pin'),
            ),
            default => [
                'method'            => SignerInput::METHOD_PAD,
                'user_id'           => null,
                'user_signature_id' => null,
                'signer_name'       => (string) $input->name,
                'signer_document'   => (string) $input->document,
                'signature_data'    => (string) $input->signature,
            ],
        };
    }

    /** @return array{method: string, user_id: int, user_signature_id: int, signer_name: string, signer_document: string, signature_data: string} */
    private function fromRegistered(string $method, UserSignature $signature): array
    {
        return [
            'method'            => $method,
            'user_id'           => $signature->getUserId(),
            'user_signature_id' => $signature->getId(),
            'signer_name'       => $signature->getSignerName(),
            'signer_document'   => $signature->getDocumentType()->value . ' ' . $signature->getDocumentNumber(),
            'signature_data'    => $signature->getSignatureData(),
        ];
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
