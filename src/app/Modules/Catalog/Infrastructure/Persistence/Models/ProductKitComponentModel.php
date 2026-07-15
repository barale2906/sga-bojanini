<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKitComponentModel extends Model
{
    protected $table = 'product_kit_components';

    protected $fillable = [
        'kit_generic_id',
        'component_generic_id',
        'quantity_per_kit',
        'sort_order',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function kitGeneric(): BelongsTo
    {
        return $this->belongsTo(GenericProductModel::class, 'kit_generic_id');
    }

    public function componentGeneric(): BelongsTo
    {
        return $this->belongsTo(GenericProductModel::class, 'component_generic_id');
    }
}
