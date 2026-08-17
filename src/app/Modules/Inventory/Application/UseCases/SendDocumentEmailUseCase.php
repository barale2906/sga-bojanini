<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Mail\MovementDocumentMail;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use DomainException;
use Illuminate\Support\Facades\Mail;

class SendDocumentEmailUseCase
{
    public function execute(int $documentId, array $recipients): MovementDocumentModel
    {
        $document = MovementDocumentModel::with([
            'movements.variant.genericProduct',
            'movements.batch',
            'movements.locationFrom',
            'movements.locationTo',
            'warehouse',
            'warehouseTo',
            'costCenter',
            'medicalService',
            'user',
            'signatures',
        ])->findOrFail($documentId);

        if ($document->status !== MovementStatus::CONFIRMED) {
            throw new DomainException('Solo se pueden enviar comprobantes de documentos confirmados.');
        }

        Mail::to($recipients)->queue(new MovementDocumentMail($document));

        return $document;
    }
}
