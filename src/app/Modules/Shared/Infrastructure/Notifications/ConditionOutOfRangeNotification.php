<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class ConditionOutOfRangeNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(private readonly string $message) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: true);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'condition_out_of_range',
            'title'    => 'Condición fuera de rango',
            'message'  => $this->message,
            'severity' => 'critical',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta de condiciones de almacenamiento')
            ->line($this->message);
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: 'Condición fuera de rango',
                body: $this->message,
            ),
        ))->data(['type' => 'condition_out_of_range']);
    }
}
