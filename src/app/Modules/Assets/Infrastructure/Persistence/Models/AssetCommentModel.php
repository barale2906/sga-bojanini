<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Comentario acumulable del activo: solo inserción, no se edita ni elimina. */
class AssetCommentModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'asset_comments';

    protected $fillable = ['asset_id', 'comment', 'user_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
