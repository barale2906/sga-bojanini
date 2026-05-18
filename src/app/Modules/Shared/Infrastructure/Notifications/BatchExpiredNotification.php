<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BatchExpiredNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly string $productName,
        private readonly string $batchNumber,
        private readonly string $expiryDate,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'batch_expired',
            'title'    => 'Lote vencido',
            'message'  => "El lote {$this->batchNumber} de {$this->productName} venció el {$this->expiryDate}.",
            'severity' => 'critical',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Lote vencido: {$this->productName}")
            ->line("El lote {$this->batchNumber} venció el {$this->expiryDate}.");
    }
}
