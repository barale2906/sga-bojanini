<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resumen de garantías y certificados de calibración próximos a vencer. */
class AssetExpirationAlertNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    /**
     * @param string[] $warranties
     * @param string[] $certificates
     */
    public function __construct(
        private readonly array $warranties,
        private readonly array $certificates,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'asset_expiration_alert',
            'title'        => 'Vencimientos de activos',
            'message'      => count($this->warranties) . ' garantía(s) y ' . count($this->certificates) . ' certificado(s) de calibración por vencer.',
            'warranties'   => $this->warranties,
            'certificates' => $this->certificates,
            'severity'     => 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Garantías y certificados de activos por vencer')
            ->greeting('Vencimientos próximos');

        foreach (['Garantías' => $this->warranties, 'Certificados de calibración' => $this->certificates] as $title => $lines) {
            if ($lines === []) {
                continue;
            }

            $mail->line("**{$title}:**");

            foreach ($lines as $line) {
                $mail->line("• {$line}");
            }
        }

        return $mail;
    }
}
