<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierModel extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'tax_id',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariantModel::class, 'product_variant_supplier', 'supplier_id', 'product_variant_id')
            ->withPivot(['supplier_sku', 'lead_time_days', 'unit_price', 'is_preferred', 'product_presentation_id'])
            ->withTimestamps();
    }
}
