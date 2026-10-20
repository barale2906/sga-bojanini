<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resumen diario de mantenimientos de activos vencidos y próximos. */
class AssetMaintenanceAlertNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    /** Máximo de líneas por grupo en el mensaje (el resto se resume). */
    private const MAX_LINES = 20;

    /**
     * @param string[] $overdue
     * @param string[] $upcoming
     */
    public function __construct(
        private readonly array $overdue,
        private readonly array $upcoming,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'asset_maintenance_alert',
            'title'    => 'Mantenimientos de activos',
            'message'  => count($this->overdue) . ' vencido(s) y ' . count($this->upcoming) . ' próximo(s) en los siguientes días.',
            'overdue'  => array_slice($this->overdue, 0, self::MAX_LINES),
            'upcoming' => array_slice($this->upcoming, 0, self::MAX_LINES),
            'severity' => $this->overdue !== [] ? 'critical' : 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Mantenimientos de activos pendientes')
            ->greeting('Cronograma de mantenimiento');

        $this->appendSection($mail, 'Vencidos', $this->overdue);
        $this->appendSection($mail, 'Próximos', $this->upcoming);

        return $mail;
    }

    private function appendSection(MailMessage $mail, string $title, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $mail->line("**{$title} (" . count($lines) . '):**');

        foreach (array_slice($lines, 0, self::MAX_LINES) as $line) {
            $mail->line("• {$line}");
        }

        if (count($lines) > self::MAX_LINES) {
            $mail->line('… y ' . (count($lines) - self::MAX_LINES) . ' más.');
        }
    }
}
