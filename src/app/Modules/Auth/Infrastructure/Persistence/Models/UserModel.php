<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property bool $is_active
 */
class UserModel extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Almacenes a los que este usuario tiene acceso explícito. */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(WarehouseModel::class, 'user_warehouse', 'user_id', 'warehouse_id')->withTimestamps();
    }

    /** Sensores (equipos de monitoreo) a los que este usuario tiene acceso explícito. */
    public function sensors(): BelongsToMany
    {
        return $this->belongsToMany(SensorModel::class, 'user_sensor', 'user_id', 'sensor_id')->withTimestamps();
    }
}
