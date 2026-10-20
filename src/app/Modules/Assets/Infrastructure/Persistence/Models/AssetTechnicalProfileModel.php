<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTechnicalProfileModel extends Model
{
    use Auditable;

    /** Campos editables de la hoja de vida (comunes + biomédicos + cómputo). */
    public const FIELDS = [
        'manufacturer', 'country_of_origin', 'manufacture_year', 'useful_life_years', 'acquisition_mode',
        'technical_specs', 'accessories', 'usage_recommendations',
        'invima_registry', 'risk_class', 'biomedical_class', 'power_source', 'voltage', 'current', 'power',
        'frequency', 'requires_calibration', 'operating_conditions',
        'processor', 'ram', 'storage', 'operating_system', 'ip_address', 'mac_address', 'hostname', 'licenses',
    ];

    /** Campos que solo aplican a equipos biomédicos. */
    public const BIOMEDICAL_FIELDS = [
        'invima_registry', 'risk_class', 'biomedical_class', 'power_source', 'voltage', 'current', 'power',
        'frequency', 'requires_calibration', 'operating_conditions',
    ];

    /** Campos que solo aplican a equipos de cómputo. */
    public const COMPUTING_FIELDS = [
        'processor', 'ram', 'storage', 'operating_system', 'ip_address', 'mac_address', 'hostname', 'licenses',
    ];

    protected $table = 'asset_technical_profiles';

    protected $fillable = ['asset_id', ...self::FIELDS];

    protected function casts(): array
    {
        return [
            'technical_specs'      => 'array',
            'licenses'             => 'array',
            'requires_calibration' => 'boolean',
            'manufacture_year'     => 'integer',
            'useful_life_years'    => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_id');
    }
}
