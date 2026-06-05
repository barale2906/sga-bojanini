<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientProcedureRecordModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'patient_procedure_records';

    protected $fillable = [
        'medical_service_id',
        'patient_external_id',
        'patient_document',
        'quantity',
        'unit_price',
        'total',
        'service_date',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'float',
            'unit_price'   => 'float',
            'total'        => 'float',
            'service_date' => 'date',
            'is_active'    => 'boolean',
        ];
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalServiceModel::class, 'medical_service_id');
    }
}
