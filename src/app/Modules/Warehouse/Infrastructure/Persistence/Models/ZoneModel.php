<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ZoneModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'zones';

    protected $fillable = [
        'warehouse_id',
        'name',
        'code',
        'type',
        'temp_min',
        'temp_max',
        'humidity_min',
        'humidity_max',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'temp_min'      => 'decimal:2',
            'temp_max'      => 'decimal:2',
            'humidity_min'  => 'decimal:2',
            'humidity_max'  => 'decimal:2',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(LocationModel::class, 'zone_id');
    }
}
