<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resumen de certificados de calibración de equipos de monitoreo por vencer o vencidos. */
class SensorCalibrationAlertNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    /**
     * @param string[] $expiring Líneas "equipo — vence AAAA-MM-DD (en N días)"
     * @param string[] $expired  Líneas "equipo — venció AAAA-MM-DD"
     */
    public function __construct(
        private readonly array $expiring,
        private readonly array $expired,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        $parts = [];

        if ($this->expired !== []) {
            $parts[] = count($this->expired) . ' vencido(s)';
        }

        if ($this->expiring !== []) {
            $parts[] = count($this->expiring) . ' por vencer';
        }

        return [
            'type'     => 'sensor_calibration_alert',
            'title'    => 'Calibración de equipos de monitoreo',
            'message'  => 'Certificados de calibración: ' . implode(' y ', $parts) . '.',
            'expiring' => $this->expiring,
            'expired'  => $this->expired,
            'severity' => $this->expired !== [] ? 'critical' : 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Certificados de calibración de equipos de monitoreo')
            ->greeting('Calibración de equipos de monitoreo');

        foreach (['Vencidos' => $this->expired, 'Por vencer' => $this->expiring] as $title => $lines) {
            if ($lines === []) {
                continue;
            }

            $mail->line("**{$title}:**");

            foreach ($lines as $line) {
                $mail->line("• {$line}");
            }
        }

        return $mail->line('Cargue el nuevo certificado desde Monitoreo Ambiental → Sensores → Certificados de calibración.');
    }
}
