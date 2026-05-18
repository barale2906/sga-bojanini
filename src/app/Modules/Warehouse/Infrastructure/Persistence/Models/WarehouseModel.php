<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseModel extends Model
{
    use SoftDeletes;

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
}
