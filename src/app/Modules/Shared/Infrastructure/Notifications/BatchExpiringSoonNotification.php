<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class BatchExpiringSoonNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $productName,
        private readonly string $batchNumber,
        private readonly string $expiryDate,
        private readonly int $daysUntilExpiry,
        private readonly string $warehouseName,
        private readonly int $currentQuantity,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: true);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'              => 'batch_expiring_soon',
            'title'             => 'Lote próximo a vencer',
            'message'           => "El lote {$this->batchNumber} de {$this->productName} vence el {$this->expiryDate} ({$this->daysUntilExpiry} días).",
            'product_name'      => $this->productName,
            'batch_number'      => $this->batchNumber,
            'expiry_date'       => $this->expiryDate,
            'days_until_expiry' => $this->daysUntilExpiry,
            'severity'          => $this->daysUntilExpiry <= 7 ? 'critical' : 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Lote próximo a vencer: {$this->productName}")
            ->greeting('Alerta de vencimiento')
            ->line("El lote {$this->batchNumber} del producto {$this->productName} vence el {$this->expiryDate}.")
            ->line("Quedan {$this->currentQuantity} unidades en {$this->warehouseName}.");
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: "Lote por vencer: {$this->productName}",
                body: "Lote {$this->batchNumber} vence el {$this->expiryDate}.",
            ),
        ))->data(['type' => 'batch_expiring_soon', 'batch_number' => $this->batchNumber]);
    }
}
