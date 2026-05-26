<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'base_unit_id',
        'product_type',
        'name',
        'code',
        'sku',
        'description',
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
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasureModel::class, 'base_unit_id');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(SupplierModel::class, 'product_supplier', 'product_id', 'supplier_id')
            ->withPivot(['supplier_sku', 'lead_time_days', 'unit_price', 'is_preferred', 'product_presentation_id'])
            ->withTimestamps();
    }

    public function presentations(): BelongsToMany
    {
        return $this->belongsToMany(ProductPresentationModel::class, 'product_presentation', 'product_id', 'presentation_id')
            ->withPivot(['is_purchase_default', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function kitComponents(): HasMany
    {
        return $this->hasMany(ProductKitComponentModel::class, 'kit_product_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function stockSummaries(): HasMany
    {
        return $this->hasMany(StockSummaryModel::class, 'product_id');
    }
}
