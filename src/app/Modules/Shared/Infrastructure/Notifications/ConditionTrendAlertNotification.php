<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConditionTrendAlertNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $sensorCode,
        private readonly string $direction,
        private readonly string $confidence,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'condition_trend_alert',
            'title'   => 'Tendencia en sensor',
            'message' => "Sensor {$this->sensorCode}: tendencia {$this->direction} (confianza: {$this->confidence}).",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Tendencia detectada: {$this->sensorCode}")
            ->line("Tendencia {$this->direction} con confianza {$this->confidence}.");
    }
}
