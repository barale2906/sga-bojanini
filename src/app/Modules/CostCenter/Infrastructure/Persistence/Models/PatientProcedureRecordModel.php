<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
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
        'movement_document_id',
        'patient_external_id',
        'patient_document',
        'patient_first_name',
        'patient_last_name',
        'patient_email',
        'patient_address',
        'patient_phone',
        'quantity',
        'unit_price',
        'total',
        'service_date',
        'notes',
        'seller',
        'referrer',
        'is_active',
        'order_number',
        'discount_type',
        'discount_value',
        'discount_amount',
        'net_total',
        'discount_status',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'float',
            'unit_price'      => 'float',
            'total'           => 'float',
            'discount_value'  => 'float',
            'discount_amount' => 'float',
            'net_total'       => 'float',
            'service_date'    => 'date',
            'approved_at'     => 'datetime',
            'is_active'       => 'boolean',
        ];
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalServiceModel::class, 'medical_service_id');
    }

    public function movementDocument(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel::class, 'movement_document_id');
    }

    public function scopeForSeller(Builder $query, string $seller): Builder
    {
        return $query->where('seller', 'like', "%{$seller}%");
    }

    public function scopeForReferrer(Builder $query, string $referrer): Builder
    {
        return $query->where('referrer', 'like', "%{$referrer}%");
    }
}
