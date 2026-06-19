<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Shared\Domain\Enums\ReportExportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Representa una solicitud de reporte (síncrona o en background) y su
 * ciclo de vida: queued -> processing -> ready|failed.
 *
 * @property string $id
 * @property int $user_id
 * @property string $type
 * @property string $format
 * @property array<string, mixed>|null $filters
 * @property string $status
 * @property string|null $file_path
 * @property string $file_disk
 * @property int|null $file_size
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class ReportExportModel extends Model
{
    use HasUuids;

    protected $table = 'report_exports';

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'filters',
        'status',
        'file_path',
        'file_disk',
        'file_size',
        'error_message',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'file_size'    => 'integer',
        'expires_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function isReady(): bool
    {
        return $this->status === ReportExportStatus::Ready->value;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
