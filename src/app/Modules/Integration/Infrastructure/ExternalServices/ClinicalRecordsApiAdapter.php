<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use App\Modules\Integration\Domain\Ports\ClinicalRecordsServiceInterface;
use App\Modules\Integration\Infrastructure\Persistence\Models\ExternalIntegrationModel;
use App\Modules\Integration\Infrastructure\Persistence\Models\IntegrationLogModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClinicalRecordsApiAdapter implements ClinicalRecordsServiceInterface
{
    private ?ExternalIntegrationModel $integration = null;

    private function getIntegration(): ExternalIntegrationModel
    {
        if ($this->integration === null) {
            $this->integration = ExternalIntegrationModel::where('type', 'clinical_records')
                ->where('is_active', true)
                ->firstOrFail();
        }

        return $this->integration;
    }

    public function registerConsumption(
        string $patientId,
        string $appointmentId,
        array $items,
    ): array {
        $integration = $this->getIntegration();
        $endpoint    = '/api/clinical-records/consumption';
        $fullUrl     = rtrim($integration->base_url, '/').$endpoint;

        $payload = [
            'patient_id'     => $patientId,
            'appointment_id' => $appointmentId,
            'items'          => $items,
        ];

        $response = null;
        $error    = null;

        try {
            $response = Http::withHeaders($this->buildHeaders($integration))
                ->timeout(10)
                ->retry(3, 100)
                ->post($fullUrl, $payload);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error("Error al registrar consumo en HC: {$error}");
        }

        IntegrationLogModel::create([
            'integration_id'   => $integration->id,
            'direction'        => 'outbound',
            'endpoint'         => $fullUrl,
            'method'           => 'POST',
            'request_payload'  => $payload,
            'response_payload' => $response?->json(),
            'status_code'      => $response?->status(),
            'error_message'    => $error,
        ]);

        if ($response === null || !$response->successful()) {
            return [
                'success'   => false,
                'record_id' => null,
                'message'   => $error ?? "HTTP {$response?->status()}",
            ];
        }

        $data = $response->json('data', []);

        return [
            'success'   => true,
            'record_id' => $data['record_id'] ?? $data['id'] ?? null,
            'message'   => $data['message'] ?? 'Registrado en historias clínicas',
        ];
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
