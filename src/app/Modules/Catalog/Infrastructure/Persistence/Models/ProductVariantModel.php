<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'product_variants';

    protected $fillable = [
        'generic_product_id',
        'lab_brand',
        'brand_sku',
        'commercial_presentation',
        'serie_reference',
        'useful_life',
        'risk_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function genericProduct(): BelongsTo
    {
        return $this->belongsTo(GenericProductModel::class, 'generic_product_id');
    }

    public function sanitaryRegistrations(): HasMany
    {
        return $this->hasMany(ProductSanitaryRegistrationModel::class, 'product_variant_id')
            ->orderByDesc('expiry_date');
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(
            SupplierModel::class,
            'product_variant_supplier',
            'product_variant_id',
            'supplier_id'
        )
            ->withPivot(['supplier_sku', 'lead_time_days', 'unit_price', 'is_preferred', 'product_presentation_id'])
            ->withTimestamps();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(BatchModel::class, 'product_variant_id');
    }

    public function stockSummaries(): HasMany
    {
        return $this->hasMany(StockSummaryModel::class, 'product_variant_id');
    }
}
