<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationLogModel extends Model
{
    public $timestamps = false;

    protected $table = 'integration_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request_payload'  => 'array',
            'response_payload' => 'array',
            'created_at'       => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ExternalIntegrationModel::class, 'integration_id');
    }
}
