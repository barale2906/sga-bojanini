<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain\Ports;

use Carbon\Carbon;

interface SchedulingServiceInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingAppointments(Carbon $from, Carbon $to): array;

    /**
     * @return array<string, mixed>
     */
    public function getPatientBasicData(string $patientId): array;
}
