<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLogModel extends Model
{
    public $timestamps = false;

    protected $table = 'import_logs';

    protected $fillable = [
        'file_name',
        'entity_type',
        'total_rows',
        'success_rows',
        'error_rows',
        'errors',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }
}
