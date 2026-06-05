<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcedurePriceModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'procedure_prices';

    protected $fillable = [
        'medical_service_id',
        'unit_price',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'     => 'float',
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalServiceModel::class, 'medical_service_id');
    }
}
