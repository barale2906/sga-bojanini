<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'warehouses';

    protected $fillable = [
        'name',
        'code',
        'address',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(ZoneModel::class, 'warehouse_id');
    }

    /** Usuarios con acceso explícito a este almacén. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(UserModel::class, 'user_warehouse', 'warehouse_id', 'user_id')->withTimestamps();
    }
}
