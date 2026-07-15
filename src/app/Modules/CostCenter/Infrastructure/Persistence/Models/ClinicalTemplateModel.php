<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalTemplateModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'clinical_templates';

    protected $fillable = [
        'medical_service_id',
        'title',
        'content',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalServiceModel::class, 'medical_service_id');
    }
}
