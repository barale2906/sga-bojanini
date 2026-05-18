<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationRecipientService
{
    public function notifyByType(string $notificationType, Notification $notification): void
    {
        $roles = config("sga.notification_recipients.{$notificationType}", []);

        if ($roles === []) {
            return;
        }

        $roles = array_unique(array_merge($roles, ['super_admin']));

        $users = UserModel::role($roles)->where('is_active', true)->get();

        if ($users->isNotEmpty()) {
            NotificationFacade::send($users, $notification);
        }
    }
}
