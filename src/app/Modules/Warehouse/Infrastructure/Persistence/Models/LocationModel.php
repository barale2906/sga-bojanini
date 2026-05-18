<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocationModel extends Model
{
    use SoftDeletes;

    protected $table = 'locations';

    protected $fillable = [
        'zone_id',
        'name',
        'code',
        'capacity',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity'  => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ZoneModel::class, 'zone_id');
    }
}
