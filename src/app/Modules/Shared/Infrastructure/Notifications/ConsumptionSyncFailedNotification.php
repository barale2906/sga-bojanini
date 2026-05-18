<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsumptionSyncFailedNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $appointmentId,
        private readonly string $errorMessage,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'consumption_sync_failed',
            'title'   => 'Fallo sincronización con HC',
            'message' => "Cita {$this->appointmentId}: {$this->errorMessage}",
            'severity' => 'critical',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Error al sincronizar consumo con HC')
            ->line($this->errorMessage);
    }
}
