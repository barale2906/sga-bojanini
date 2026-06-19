<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use App\Modules\Shared\Infrastructure\Persistence\Models\ReportExportModel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * Notifica al usuario solicitante que la generación de su reporte en
 * background falló. El detalle técnico del error se registra en el log
 * del job; aquí solo se comunica un mensaje genérico al usuario.
 */
class ReportFailedNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    public function __construct(
        private readonly ReportExportModel $export,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels(includePush: true);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'        => 'report_failed',
            'title'       => 'No se pudo generar el reporte',
            'message'     => sprintf(
                'El reporte de %s en formato %s no se pudo generar. Intenta nuevamente.',
                $this->export->type,
                strtoupper($this->export->format),
            ),
            'severity'    => 'error',
            'export_id'   => $this->export->id,
            'report_type' => $this->export->type,
            'format'      => $this->export->format,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('No se pudo generar tu reporte')
            ->line(sprintf('El reporte de %s que solicitaste no se pudo generar.', $this->export->type))
            ->line('Intenta nuevamente desde el módulo de Reportes. Si el problema persiste, contacta a soporte.');
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: 'Reporte fallido',
                body: sprintf('El reporte de %s no se pudo generar.', $this->export->type),
            ),
        ))->data([
            'type'      => 'report_failed',
            'export_id' => $this->export->id,
        ]);
    }
}
