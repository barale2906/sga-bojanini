<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'locations';

    protected $fillable = [
        'zone_id',
        'name',
        'code',
        'volume_cm3',
        'max_weight_kg',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'volume_cm3'    => 'decimal:2',
            'max_weight_kg' => 'decimal:2',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ZoneModel::class, 'zone_id');
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(BatchModel::class, 'batch_location', 'location_id', 'batch_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
