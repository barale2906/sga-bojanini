<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderRejectedNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $orderCode,
        private readonly ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'purchase_order_rejected',
            'title'   => 'OC rechazada',
            'message' => "La orden {$this->orderCode} fue rechazada.".($this->reason ? " Motivo: {$this->reason}" : ''),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("OC rechazada: {$this->orderCode}")
            ->line("La orden de compra {$this->orderCode} fue rechazada.");
    }
}
