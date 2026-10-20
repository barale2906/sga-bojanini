<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

/**
 * Solicitud de traslado de un activo.
 *
 * - relocation:      `toPlaceId` y/o `toResponsibleId` (si cambia de sede se registra como site_transfer).
 * - external_repair: `externalDestination` y opcional `supplierId`, `expectedReturnDate`.
 * - loan:            `externalDestination` y opcional `expectedReturnDate`.
 * - return:          `toPlaceId` y opcional `resultingStatus`.
 * - retirement:      `reason` obligatorio; opcional `externalDestination` (disposición final).
 */
readonly class AssetTransferRequestData
{
    public function __construct(
        public string $type,
        public ?int $toPlaceId = null,
        public ?int $toResponsibleId = null,
        public ?string $externalDestination = null,
        public ?int $supplierId = null,
        public ?string $expectedReturnDate = null,
        public ?string $resultingStatus = null,
        public ?string $reason = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type:                $data['type'],
            toPlaceId:           isset($data['to_place_id']) ? (int) $data['to_place_id'] : null,
            toResponsibleId:     isset($data['to_responsible_id']) ? (int) $data['to_responsible_id'] : null,
            externalDestination: $data['external_destination'] ?? null,
            supplierId:          isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            expectedReturnDate:  $data['expected_return_date'] ?? null,
            resultingStatus:     $data['resulting_status'] ?? null,
            reason:              $data['reason'] ?? null,
        );
    }
}
