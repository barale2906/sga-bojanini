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
 * Notifica al usuario que solicitó un reporte en background que ya está
 * listo para descargar. Se envía únicamente al usuario solicitante (no
 * por rol), a diferencia de las alertas operativas del sistema.
 */
class ReportReadyNotification extends Notification
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
            'type'         => 'report_ready',
            'title'        => 'Reporte listo para descargar',
            'message'      => sprintf(
                'Tu reporte de %s en formato %s ya está disponible.',
                $this->export->type,
                strtoupper($this->export->format),
            ),
            'severity'     => 'success',
            'export_id'    => $this->export->id,
            'report_type'  => $this->export->type,
            'format'       => $this->export->format,
            'file_size'    => $this->export->file_size,
            'download_url' => url("/api/v1/reports/exports/{$this->export->id}/download"),
            'expires_at'   => $this->export->expires_at?->toIso8601String(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu reporte está listo')
            ->line(sprintf('El reporte de %s que solicitaste ya está disponible.', $this->export->type))
            ->line('Ingresa a la plataforma, ve a Reportes > Mis exportaciones y descárgalo desde allí.')
            ->line('Por seguridad, el enlace de descarga requiere tu sesión activa en el sistema.');
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(
            notification: new FcmNotification(
                title: 'Reporte listo',
                body: sprintf('Tu reporte de %s ya está disponible para descargar.', $this->export->type),
            ),
        ))->data([
            'type'      => 'report_ready',
            'export_id' => $this->export->id,
        ]);
    }
}
