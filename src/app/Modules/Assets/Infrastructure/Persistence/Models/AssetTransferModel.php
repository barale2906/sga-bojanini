<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetTransferModel extends Model
{
    use Auditable;

    protected $table = 'asset_transfers';

    protected $fillable = [
        'asset_id', 'type', 'status', 'requires_signature', 'from_place_id', 'to_place_id',
        'from_external_location', 'external_destination', 'supplier_id', 'from_responsible_id',
        'to_responsible_id', 'expected_return_date', 'resulting_status', 'reason', 'requested_by',
        'confirmed_at', 'cancelled_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'type'                 => AssetTransferType::class,
            'status'               => AssetTransferStatus::class,
            'requires_signature'   => 'boolean',
            'expected_return_date' => 'date',
            'confirmed_at'         => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_id')->withTrashed();
    }

    public function fromPlace(): BelongsTo
    {
        return $this->belongsTo(AssetPlaceModel::class, 'from_place_id')->withTrashed();
    }

    public function toPlace(): BelongsTo
    {
        return $this->belongsTo(AssetPlaceModel::class, 'to_place_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierModel::class, 'supplier_id')->withTrashed();
    }

    public function fromResponsible(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'from_responsible_id');
    }

    public function toResponsible(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'to_responsible_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'requested_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(AssetSignatureModel::class, 'signable_id')
            ->where('signable_type', AssetSignatureModel::SIGNABLE_TRANSFER);
    }

    public function isPending(): bool
    {
        return $this->status === AssetTransferStatus::PendingSignature;
    }
}
