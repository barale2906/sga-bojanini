<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Certificado de calibración de un equipo de monitoreo.
 *
 * @property int $id
 * @property int $sensor_id
 * @property string|null $certificate_number
 * @property string|null $laboratory
 * @property \Illuminate\Support\Carbon|null $calibrated_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string|null $notes
 * @property bool $is_current
 * @property \Illuminate\Support\Carbon|null $replaced_at
 * @property int|null $uploaded_by
 */
class SensorCalibrationCertificateModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'sensor_calibration_certificates';

    protected $fillable = [
        'sensor_id',
        'certificate_number',
        'laboratory',
        'calibrated_at',
        'expires_at',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'notes',
        'is_current',
        'replaced_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'calibrated_at' => 'date',
            'expires_at'    => 'date',
            'is_current'    => 'boolean',
            'replaced_at'   => 'datetime',
            'size'          => 'integer',
        ];
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(SensorModel::class, 'sensor_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'uploaded_by');
    }
}
