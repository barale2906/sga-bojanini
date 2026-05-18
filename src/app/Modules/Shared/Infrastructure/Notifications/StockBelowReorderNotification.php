<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class StockBelowReorderNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $productName,
        private readonly string $productCode,
        private readonly int $currentStock,
        private readonly int $reorderPoint,
        private readonly string $warehouseName,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: true);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'stock_below_reorder',
            'title'    => 'Stock bajo punto de reorden',
            'message'  => "{$this->productName} ({$this->productCode}): stock {$this->currentStock}, reorden {$this->reorderPoint}.",
            'severity' => 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Stock bajo: {$this->productName}")
            ->line("Stock actual: {$this->currentStock}. Punto de reorden: {$this->reorderPoint}.")
            ->line("Almacén: {$this->warehouseName}.");
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: "Stock bajo: {$this->productName}",
                body: "Quedan {$this->currentStock} unidades.",
            ),
        ))->data(['type' => 'stock_below_reorder']);
    }
}
