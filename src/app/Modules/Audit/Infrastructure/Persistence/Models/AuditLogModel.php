<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Eloquent de SOLO LECTURA para la tabla `audit_logs`.
 *
 * Los registros se crean desde el trait Auditable o AuditMiddleware.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $action
 * @property string      $auditable_type
 * @property int|null    $auditable_id
 * @property array|null  $old_values
 * @property array|null  $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 */
class AuditLogModel extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
