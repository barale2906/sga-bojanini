<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalServiceModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'medical_services';

    protected $fillable = [
        'parent_id',
        'type',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function procedurePrices(): HasMany
    {
        return $this->hasMany(ProcedurePriceModel::class, 'medical_service_id');
    }

    public function patientProcedureRecords(): HasMany
    {
        return $this->hasMany(PatientProcedureRecordModel::class, 'medical_service_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovementModel::class, 'service_id');
    }
}
