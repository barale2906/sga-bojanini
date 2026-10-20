<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Firma (snapshot) de un documento de activos: traslado o mantenimiento.
 *
 * Sin Auditable a propósito: la imagen de la firma no debe copiarse a audit_logs.
 */
class AssetSignatureModel extends Model
{
    public const SIGNABLE_TRANSFER    = 'transfer';
    public const SIGNABLE_MAINTENANCE = 'maintenance';

    public const UPDATED_AT = null;

    protected $table = 'asset_signatures';

    protected $fillable = [
        'signable_type', 'signable_id', 'role', 'method', 'user_id', 'user_signature_id',
        'signer_name', 'signer_document', 'signature_data', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_at'  => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
