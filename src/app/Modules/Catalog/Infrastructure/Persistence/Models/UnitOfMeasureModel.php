<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasureModel extends Model
{
    use Auditable;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'name',
        'abbreviation',
        'is_active',
        'is_base',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_base'   => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(ProductModel::class, 'base_unit_id');
    }
}
