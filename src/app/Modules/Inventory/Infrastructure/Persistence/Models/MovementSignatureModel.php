<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementSignatureModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movement_signatures';

    protected $fillable = [
        'movement_document_id',
        'role',
        'signer_name',
        'signer_document',
        'signature_data',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_at'  => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function movementDocument(): BelongsTo
    {
        return $this->belongsTo(MovementDocumentModel::class, 'movement_document_id');
    }
}
