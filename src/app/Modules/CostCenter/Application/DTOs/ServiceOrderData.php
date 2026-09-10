<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

use DateTimeImmutable;

readonly class ServiceOrderData
{
    public function __construct(
        public string $patientExternalId,
        public string $patientDocument,
        public string $patientFirstName,
        public string $patientLastName,
        public DateTimeImmutable $serviceDate,
        /** @var ServiceOrderLineData[] */
        public array $procedures,
        public ?string $notes = null,
        public ?string $patientEmail = null,
        public ?string $patientAddress = null,
        public ?string $patientPhone = null,
        public ?string $seller = null,
        public ?string $referrer = null,
    ) {}
}
