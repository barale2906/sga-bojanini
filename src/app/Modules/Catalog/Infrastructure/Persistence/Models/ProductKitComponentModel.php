<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductKitComponentModel extends Model
{
    protected $table = 'product_kit_components';

    protected $fillable = [
        'kit_product_id',
        'component_product_id',
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

    public function kitProduct(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'kit_product_id');
    }

    public function componentProduct(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'component_product_id');
    }
}
