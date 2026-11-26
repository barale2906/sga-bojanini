<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dispositivo móvil de un usuario, destino de las notificaciones push (FCM).
 *
 * No usa el trait Auditable: el token es un identificador sensible y no debe
 * quedar en audit_logs, que consultan otros usuarios.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property string|null $device_name
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
class UserDeviceModel extends Model
{
    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'last_seen_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
