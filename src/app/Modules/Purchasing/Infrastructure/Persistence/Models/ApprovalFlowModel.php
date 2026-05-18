<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlowModel extends Model
{
    protected $table = 'approval_flows';

    protected $fillable = [
        'name',
        'entity_type',
        'conditions',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active'  => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStepModel::class, 'approval_flow_id')->orderBy('step_order');
    }
}
