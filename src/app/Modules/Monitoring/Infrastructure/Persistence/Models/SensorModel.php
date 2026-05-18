<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorModel extends Model
{
    use Auditable;

    protected $table = 'sensors';

    protected $fillable = [
        'zone_id',
        'code',
        'name',
        'type',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ZoneModel::class, 'zone_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(SensorReadingModel::class, 'sensor_id');
    }

    public function alertRules(): HasMany
    {
        return $this->hasMany(AlertRuleModel::class, 'sensor_id');
    }
}
