<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GenericProductModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'product_generics';

    protected $fillable = [
        'category_id',
        'classification_id',
        'base_unit_id',
        'product_type',
        'name',
        'barcode',
        'description',
        'concentration',
        'pharmaceutical_form',
        'volume_cm3',
        'weight_kg',
        'requires_cold_chain',
        'reorder_point',
        'reorder_quantity',
        'min_stock',
        'max_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_cold_chain' => 'boolean',
            'is_active'           => 'boolean',
            'volume_cm3'          => 'decimal:2',
            'weight_kg'           => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(ProductClassificationModel::class, 'classification_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasureModel::class, 'base_unit_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariantModel::class, 'generic_product_id');
    }

    public function presentations(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductPresentationModel::class,
            'product_presentation',
            'generic_product_id',
            'presentation_id'
        )
            ->withPivot(['is_purchase_default', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function kitComponents(): HasMany
    {
        return $this->hasMany(ProductKitComponentModel::class, 'kit_generic_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
}
