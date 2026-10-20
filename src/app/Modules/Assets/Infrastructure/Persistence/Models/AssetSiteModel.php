<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetSiteModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'asset_sites';

    protected $fillable = ['name', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function places(): HasMany
    {
        return $this->hasMany(AssetPlaceModel::class, 'site_id');
    }
}
