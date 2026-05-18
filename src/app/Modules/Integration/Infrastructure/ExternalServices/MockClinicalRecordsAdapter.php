<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use App\Modules\Integration\Domain\Ports\ClinicalRecordsServiceInterface;

class MockClinicalRecordsAdapter implements ClinicalRecordsServiceInterface
{
    public function registerConsumption(
        string $patientId,
        string $appointmentId,
        array $items,
    ): array {
        return [
            'success'   => true,
            'record_id' => 'HC-MOCK-'.time(),
            'message'   => 'Registrado (mock)',
        ];
    }
}
