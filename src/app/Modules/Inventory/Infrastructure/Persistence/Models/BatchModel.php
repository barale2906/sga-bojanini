<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BatchModel extends Model
{
    protected $table = 'batches';

    protected $fillable = [
        'product_id',
        'lot_number',
        'expiration_date',
        'manufacturing_date',
        'quantity_received',
        'quantity_available',
        'status',
        'notes',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expiration_date'    => 'date',
            'manufacturing_date' => 'date',
            'received_at'        => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(LocationModel::class, 'batch_location', 'batch_id', 'location_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovementModel::class, 'batch_id');
    }
}
