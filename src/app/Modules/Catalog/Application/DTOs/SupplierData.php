<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

class SupplierData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $taxId = null,
        public readonly ?string $contactName = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $address = null,
        public readonly ?string $notes = null,
        public readonly string $supplierType = 'both',
    ) {}
}
