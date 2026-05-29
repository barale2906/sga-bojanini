<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductClassificationModel extends Model
{
    use Auditable;

    protected $table = 'product_classifications';

    protected $fillable = [
        'code',
        'name',
        'description',
        'has_sanitary_registration',
        'has_concentration',
        'has_risk_level',
        'has_pharma_fields',
        'has_device_fields',
        'has_lab_brand',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_sanitary_registration' => 'boolean',
            'has_concentration'         => 'boolean',
            'has_risk_level'            => 'boolean',
            'has_pharma_fields'         => 'boolean',
            'has_device_fields'         => 'boolean',
            'has_lab_brand'             => 'boolean',
            'is_active'                 => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductModel::class, 'classification_id');
    }
}
