<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertRuleModel extends Model
{
    use Auditable;

    protected $table = 'sensor_alert_rules';

    protected $fillable = [
        'sensor_id',
        'condition_type',
        'threshold',
        'consecutive_readings',
        'notification_channels',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold'              => 'decimal:2',
            'notification_channels'  => 'array',
            'is_active'              => 'boolean',
            'consecutive_readings'   => 'integer',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(SensorModel::class, 'sensor_id');
    }
}
