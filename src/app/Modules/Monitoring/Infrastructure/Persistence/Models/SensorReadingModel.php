<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SensorReadingModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sensor_readings';

    protected $fillable = [
        'sensor_id',
        'value',
        'reading_source',
        'recorded_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'value'       => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(SensorModel::class, 'sensor_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
