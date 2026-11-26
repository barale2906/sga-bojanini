<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Listeners;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserDeviceModel;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Elimina el token push que FCM reporta como inválido o desconocido
 * (app desinstalada, token vencido), para no volver a intentar enviarle.
 */
class PruneInvalidDeviceToken
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport) {
            return;
        }

        if ($report->messageTargetWasInvalid() || $report->messageWasSentToUnknownToken()) {
            UserDeviceModel::where('token', $report->target()->value())->delete();
        }
    }
}
