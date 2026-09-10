<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceOrderDiscountApprovedNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $orderNumber,
        private readonly string $approverName,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: false);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'service_order_discount_approved',
            'title'   => 'Descuento aprobado',
            'message' => "El descuento de la orden {$this->orderNumber} fue aprobado por {$this->approverName}.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Descuento aprobado: Orden {$this->orderNumber}")
            ->line("El descuento de la orden {$this->orderNumber} fue aprobado por {$this->approverName}.");
    }
}
