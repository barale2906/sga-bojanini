<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class PurchaseOrderPendingApprovalNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $orderCode,
        private readonly float $totalAmount,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: true);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'purchase_order_pending_approval',
            'title'   => 'OC pendiente de aprobación',
            'message' => "La orden {$this->orderCode} requiere aprobación (total: {$this->totalAmount}).",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("OC pendiente: {$this->orderCode}")
            ->line("La orden de compra {$this->orderCode} está pendiente de aprobación.");
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: 'OC pendiente de aprobación',
                body: "Orden {$this->orderCode}",
            ),
        ))->data(['type' => 'purchase_order_pending_approval']);
    }
}
