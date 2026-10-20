<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\AssetDocumentType;
use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetDocumentModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'asset_documents';

    protected $fillable = [
        'asset_id', 'maintenance_id', 'document_type', 'title', 'file_path', 'original_name',
        'mime_type', 'size', 'issued_at', 'expires_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => AssetDocumentType::class,
            'issued_at'     => 'date',
            'expires_at'    => 'date',
            'size'          => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_id')->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'uploaded_by');
    }
}
