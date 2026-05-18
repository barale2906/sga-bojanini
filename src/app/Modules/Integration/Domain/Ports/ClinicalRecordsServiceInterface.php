<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain\Ports;

interface ClinicalRecordsServiceInterface
{
    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array{success: bool, record_id: string|null, message: string}
     */
    public function registerConsumption(
        string $patientId,
        string $appointmentId,
        array $items,
    ): array;
}
