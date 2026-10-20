<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

/**
 * Datos para registrar o actualizar un activo.
 *
 * `placeId`, `responsibleId`, `status` e `initialComment` solo se usan al
 * registrar: después la ubicación/responsable cambian por traslado y el
 * estado por cambio de estado o baja.
 */
readonly class AssetData
{
    public function __construct(
        public string $name,
        public int $categoryId,
        public ?int $placeId = null,
        public ?int $responsibleId = null,
        public ?string $status = null,
        public ?string $description = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $serial = null,
        public ?string $acquisitionDate = null,
        public ?string $acquisitionValue = null,
        public ?int $supplierId = null,
        public ?string $invoiceNumber = null,
        public ?string $warrantyUntil = null,
        public ?string $notes = null,
        public ?string $initialComment = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name:             $data['name'],
            categoryId:       (int) $data['category_id'],
            placeId:          isset($data['place_id']) ? (int) $data['place_id'] : null,
            responsibleId:    isset($data['responsible_id']) ? (int) $data['responsible_id'] : null,
            status:           $data['status'] ?? null,
            description:      $data['description'] ?? null,
            brand:            $data['brand'] ?? null,
            model:            $data['model'] ?? null,
            serial:           isset($data['serial']) ? (string) $data['serial'] : null,
            acquisitionDate:  $data['acquisition_date'] ?? null,
            acquisitionValue: isset($data['acquisition_value']) ? (string) $data['acquisition_value'] : null,
            supplierId:       isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            invoiceNumber:    isset($data['invoice_number']) ? (string) $data['invoice_number'] : null,
            warrantyUntil:    $data['warranty_until'] ?? null,
            notes:            $data['notes'] ?? null,
            initialComment:   $data['comment'] ?? null,
        );
    }
}
