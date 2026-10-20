<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetCategoryModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'asset_categories';

    protected $fillable = ['code', 'name', 'asset_type', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'is_active'  => 'boolean',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssetModel::class, 'category_id');
    }
}
