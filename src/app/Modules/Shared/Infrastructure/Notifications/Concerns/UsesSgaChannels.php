<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Notifications\Concerns;

use NotificationChannels\Fcm\FcmChannel;

trait UsesSgaChannels
{
    /**
     * @return array<int, string>
     */
    protected function sgaChannels(bool $includePush = false): array
    {
        $channels = ['database', 'mail'];

        if ($includePush && $this->fcmIsConfigured()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    private function fcmIsConfigured(): bool
    {
        $path = config('sga.firebase.credentials');

        if ($path === null || $path === '') {
            return false;
        }

        return file_exists($path) || file_exists(storage_path($path));
    }
}
