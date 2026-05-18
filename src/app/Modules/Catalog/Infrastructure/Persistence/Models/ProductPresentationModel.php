<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductPresentationModel extends Model
{
    protected $table = 'product_presentations';

    protected $fillable = [
        'product_id',
        'parent_id',
        'name',
        'code',
        'units_of_measure_id',
        'quantity_per_parent',
        'factor_to_base',
        'level',
        'is_purchase_default',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_purchase_default' => 'boolean',
            'is_active'           => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductModel::class, 'product_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasureModel::class, 'units_of_measure_id');
    }
}
