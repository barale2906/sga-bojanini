<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalIntegrationModel extends Model
{
    protected $table = 'external_integrations';

    protected $fillable = [
        'name',
        'type',
        'base_url',
        'auth_type',
        'credentials',
        'is_active',
        'last_sync_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLogModel::class, 'integration_id');
    }
}
