<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use Carbon\Carbon;

class MockSchedulingAdapter implements SchedulingServiceInterface
{
    public function getUpcomingAppointments(Carbon $from, Carbon $to): array
    {
        $services = [
            ['type' => 'Consulta Dermatológica', 'products' => ['Guantes', 'Bata desechable']],
            ['type' => 'Aplicación de Botox', 'products' => ['Botox 100U', 'Jeringa 1ml', 'Algodón']],
            ['type' => 'Peeling Químico', 'products' => ['Ácido Glicólico', 'Crema neutralizante', 'Gasas']],
            ['type' => 'Láser CO2', 'products' => ['Crema anestésica', 'Gasas estériles', 'Solución salina']],
            ['type' => 'Mesoterapia', 'products' => ['Ácido Hialurónico', 'Jeringa 1ml', 'Algodón']],
        ];

        $appointments = [];
        $daysRange = max(1, (int) $from->diffInDays($to));

        for ($i = 0; $i < min($daysRange * 8, 50); $i++) {
            $service = $services[array_rand($services)];
            $appointments[] = [
                'appointment_id' => 'CITA-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'patient_id'     => 'PAC-'.str_pad((string) rand(1, 500), 5, '0', STR_PAD_LEFT),
                'patient_name'   => 'Paciente de Prueba #'.($i + 1),
                'service_type'   => $service['type'],
                'scheduled_at'   => $from->copy()->addDays(rand(0, max(0, $daysRange - 1)))
                    ->setTime(rand(8, 17), rand(0, 3) * 15)->toIso8601String(),
                'status'         => ['confirmed', 'confirmed', 'confirmed', 'pending'][rand(0, 3)],
            ];
        }

        return $appointments;
    }

    public function getPatientBasicData(string $patientId): array
    {
        return [
            'patient_id'      => $patientId,
            'name'            => 'Paciente Mock '.$patientId,
            'document_type'   => 'CC',
            'document_number' => '10'.rand(10000000, 99999999),
        ];
    }
}
