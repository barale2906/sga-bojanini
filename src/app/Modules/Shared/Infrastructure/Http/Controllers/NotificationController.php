<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $notifications = $user->notifications()
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = collect($notifications->items())->map(fn ($n) => [
            'id'         => $n->id,
            'type'       => $n->data['type'] ?? class_basename($n->type),
            'title'      => $n->data['title'] ?? '',
            'message'    => $n->data['message'] ?? '',
            'severity'   => $n->data['severity'] ?? 'info',
            'read_at'    => $n->read_at?->format('Y-m-d H:i:s'),
            'created_at' => $n->created_at->format('Y-m-d H:i:s'),
            'data'       => $n->data,
        ]);

        return $this->success([
            'items' => $items,
            'meta'  => [
                'current_page' => $notifications->currentPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'last_page'    => $notifications->lastPage(),
            ],
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Auth::user()->unreadNotifications()->count();

        return $this->success(['count' => $count]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();

        if ($notification === null) {
            return $this->error('Notificación no encontrada', 404);
        }

        $notification->markAsRead();

        return $this->success(null, 'Notificación marcada como leída');
    }

    public function markAllAsRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return $this->success(null, 'Todas las notificaciones marcadas como leídas');
    }
}
