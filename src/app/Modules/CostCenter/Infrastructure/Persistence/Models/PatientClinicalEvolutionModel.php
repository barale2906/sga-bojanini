<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientClinicalEvolutionModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'patient_clinical_evolutions';

    protected $fillable = [
        'patient_procedure_record_id',
        'content',
        'user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function procedureRecord(): BelongsTo
    {
        return $this->belongsTo(PatientProcedureRecordModel::class, 'patient_procedure_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
