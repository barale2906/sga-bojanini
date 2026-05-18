<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRecordModel extends Model
{
    protected $table = 'approval_records';

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'approval_step_id',
        'user_id',
        'status',
        'comments',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepModel::class, 'approval_step_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
