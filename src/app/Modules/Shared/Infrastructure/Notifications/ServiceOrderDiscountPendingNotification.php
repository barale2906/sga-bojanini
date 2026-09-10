<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class ServiceOrderDiscountPendingNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $orderNumber,
        private readonly string $patientName,
        private readonly float $totalDiscount,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: true);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'service_order_discount_pending',
            'title'   => 'Descuento pendiente de aprobación',
            'message' => "La orden {$this->orderNumber} del paciente {$this->patientName} tiene un descuento de $" . number_format($this->totalDiscount, 2, '.', ',') . ' pendiente de aprobación.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Descuento pendiente: Orden {$this->orderNumber}")
            ->line("La orden de servicio {$this->orderNumber} del paciente {$this->patientName}")
            ->line('tiene un descuento de $' . number_format($this->totalDiscount, 2, '.', ',') . ' pendiente de aprobación.')
            ->action('Ver orden', url('/service-orders/' . $this->orderNumber));
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: 'Descuento pendiente',
                body: "Orden {$this->orderNumber} — {$this->patientName}",
            ),
        ))->data([
            'type'         => 'service_order_discount_pending',
            'order_number' => $this->orderNumber,
        ]);
    }
}
