<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetPlaceModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'asset_places';

    protected $fillable = ['site_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(AssetSiteModel::class, 'site_id')->withTrashed();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssetModel::class, 'current_place_id');
    }

    /** "Sede → Sitio" para mostrar en listados, actas y PDF. */
    public function fullName(): string
    {
        return ($this->site?->name ?? '—') . ' → ' . $this->name;
    }
}
