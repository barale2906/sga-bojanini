<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitácora de eventos del activo (solo lectura). */
class AssetMovementModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'asset_movements';

    protected $fillable = ['asset_id', 'type', 'from_status', 'to_status', 'transfer_id', 'maintenance_id', 'description', 'user_id'];

    protected function casts(): array
    {
        return [
            'type'       => AssetMovementType::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
