<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumptionRecordModel extends Model
{
    protected $table = 'consumption_records';

    protected $fillable = [
        'appointment_id',
        'patient_identifier',
        'service_type',
        'product_variant_id',
        'batch_id',
        'quantity',
        'user_id',
        'consumed_at',
        'synced_to_hc_at',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'consumed_at'     => 'datetime',
            'synced_to_hc_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariantModel::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchModel::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
