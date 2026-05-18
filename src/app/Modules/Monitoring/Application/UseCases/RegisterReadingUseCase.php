<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\ReadingData;
use App\Modules\Monitoring\Domain\Entities\SensorReading;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\ConditionAlertService;
use App\Modules\Shared\Application\Services\NotificationRecipientService;
use App\Modules\Shared\Infrastructure\Notifications\ConditionOutOfRangeNotification;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;

/**
 * Caso de uso: Registrar una lectura manual de un sensor.
 *
 * Un operador abre la app, selecciona un sensor y registra la lectura
 * que vio en el termómetro o higrómetro. Después de guardarla, el sistema
 * evalúa si la lectura está fuera de rango y genera alertas si es necesario.
 */
class RegisterReadingUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly SensorReadingRepositoryInterface $readingRepository,
        private readonly ConditionAlertService $alertService,
        private readonly NotificationRecipientService $notificationService,
    ) {}

    /**
     * @param ReadingData $data Datos de la lectura
     * @return array{reading: SensorReading, alerts: array}
     *
     * @throws \DomainException Si el sensor no existe o está inactivo
     */
    public function execute(ReadingData $data): array
    {
        // Verificar que el sensor existe y está activo
        $sensor = $this->sensorRepository->findById($data->sensorId);
        if ($sensor === null) {
            throw new \DomainException("El sensor con ID {$data->sensorId} no existe.");
        }
        if (!$sensor->isActive()) {
            throw new \DomainException("El sensor '{$sensor->getCode()}' está inactivo.");
        }

        // Crear la entidad de lectura
        $reading = new SensorReading(
            id: null,
            sensorId: $data->sensorId,
            value: $data->value,
            readingSource: $data->readingSource,
            recordedAt: new DateTimeImmutable($data->recordedAt),
            userId: Auth::id(),
        );

        // Guardar en la base de datos
        $reading = $this->readingRepository->save($reading);

        $alerts = $this->alertService->evaluateReading($reading);

        foreach ($alerts as $alert) {
            if (($alert['type'] ?? '') === 'out_of_range') {
                $this->notificationService->notifyByType(
                    'condition_out_of_range',
                    new ConditionOutOfRangeNotification($alert['message']),
                );
            }
        }

        return [
            'reading' => $reading,
            'alerts'  => $alerts,
        ];
    }
}
