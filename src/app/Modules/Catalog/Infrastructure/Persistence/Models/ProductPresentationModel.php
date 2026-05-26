<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductPresentationModel extends Model
{
    protected $table = 'product_presentations';

    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'units_of_measure_id',
        'quantity_per_parent',
        'factor_to_base',
        'level',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(ProductModel::class, 'product_presentation', 'presentation_id', 'product_id')
            ->withPivot(['is_purchase_default', 'sort_order'])
            ->withTimestamps();
    }
}
