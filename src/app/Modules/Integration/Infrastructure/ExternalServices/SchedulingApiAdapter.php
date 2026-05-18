<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use App\Modules\Integration\Infrastructure\Persistence\Models\ExternalIntegrationModel;
use App\Modules\Integration\Infrastructure\Persistence\Models\IntegrationLogModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SchedulingApiAdapter implements SchedulingServiceInterface
{
    private ?ExternalIntegrationModel $integration = null;

    private function getIntegration(): ExternalIntegrationModel
    {
        if ($this->integration === null) {
            $this->integration = ExternalIntegrationModel::where('type', 'scheduling')
                ->where('is_active', true)
                ->firstOrFail();
        }

        return $this->integration;
    }

    public function getUpcomingAppointments(Carbon $from, Carbon $to): array
    {
        $integration = $this->getIntegration();
        $endpoint    = '/api/appointments';
        $fullUrl     = rtrim($integration->base_url, '/').$endpoint;

        $params = [
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'status'    => 'confirmed,pending',
        ];

        $response = null;
        $error    = null;

        try {
            $response = Http::withHeaders($this->buildHeaders($integration))
                ->timeout(10)
                ->retry(3, 100)
                ->get($fullUrl, $params);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error("Error al conectar con sistema de agendamiento: {$error}");
        }

        IntegrationLogModel::create([
            'integration_id'   => $integration->id,
            'direction'        => 'inbound',
            'endpoint'         => $fullUrl,
            'method'           => 'GET',
            'request_payload'  => $params,
            'response_payload' => $response?->json(),
            'status_code'      => $response?->status(),
            'error_message'    => $error,
        ]);

        if ($response === null || !$response->successful()) {
            throw new \RuntimeException(
                'Error al obtener citas del sistema de agendamiento: '
                .($error ?? "HTTP {$response?->status()}")
            );
        }

        return $response->json('data', []);
    }

    public function getPatientBasicData(string $patientId): array
    {
        $integration = $this->getIntegration();
        $endpoint    = "/api/patients/{$patientId}";
        $fullUrl     = rtrim($integration->base_url, '/').$endpoint;

        try {
            $response = Http::withHeaders($this->buildHeaders($integration))
                ->timeout(10)
                ->retry(3, 100)
                ->get($fullUrl);

            IntegrationLogModel::create([
                'integration_id'   => $integration->id,
                'direction'        => 'inbound',
                'endpoint'         => $fullUrl,
                'method'           => 'GET',
                'request_payload'  => null,
                'response_payload' => $response->json(),
                'status_code'      => $response->status(),
                'error_message'    => null,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()}");
            }

            return $response->json('data', []);
        } catch (\Throwable $e) {
            Log::error("Error al obtener datos de paciente {$patientId}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function buildHeaders(ExternalIntegrationModel $integration): array
    {
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $credentials = json_decode(decrypt($integration->credentials), true);

        return match ($integration->auth_type) {
            'bearer'  => array_merge($headers, ['Authorization' => "Bearer {$credentials['token']}"]),
            'api_key' => array_merge($headers, ['X-API-Key' => $credentials['api_key']]),
            'basic'   => array_merge($headers, [
                'Authorization' => 'Basic '.base64_encode("{$credentials['username']}:{$credentials['password']}"),
            ]),
            default   => $headers,
        };
    }
}
