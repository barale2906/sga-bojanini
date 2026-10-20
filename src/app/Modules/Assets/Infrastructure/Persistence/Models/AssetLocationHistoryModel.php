<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tramo del historial de ubicaciones de un activo (solo lo escribe AssetLocationService). */
class AssetLocationHistoryModel extends Model
{
    protected $table = 'asset_location_history';

    protected $fillable = ['asset_id', 'place_id', 'external_location', 'from_date', 'to_date', 'transfer_id', 'assigned_by'];

    protected function casts(): array
    {
        return [
            'from_date' => 'datetime',
            'to_date'   => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(AssetPlaceModel::class, 'place_id')->withTrashed();
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(AssetTransferModel::class, 'transfer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'assigned_by');
    }
}
