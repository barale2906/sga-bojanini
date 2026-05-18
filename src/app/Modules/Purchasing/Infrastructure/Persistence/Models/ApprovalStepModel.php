<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class ApprovalStepModel extends Model
{
    protected $table = 'approval_steps';

    protected $fillable = [
        'approval_flow_id',
        'step_order',
        'role_id',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlowModel::class, 'approval_flow_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
