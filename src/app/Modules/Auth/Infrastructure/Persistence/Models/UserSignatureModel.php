<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión de la firma registrada de un usuario.
 *
 * No usa el trait Auditable a propósito: este escribiría en audit_logs todos
 * los atributos, incluidos la imagen de la firma y el HMAC del PIN, y esos
 * logs los consultan otros usuarios. El versionado de la tabla (las filas no
 * se editan, se desactivan) ya deja el rastro de cada cambio.
 *
 * @property int $id
 * @property int $user_id
 * @property IdentityDocumentType $document_type
 * @property string $document_number
 * @property string $signature_data
 * @property string|null $pin_lookup
 * @property \Illuminate\Support\Carbon|null $pin_generated_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $deactivated_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class UserSignatureModel extends Model
{
    protected $table = 'user_signatures';

    protected $fillable = [
        'user_id',
        'document_type',
        'document_number',
        'signature_data',
        'pin_lookup',
        'pin_generated_at',
        'is_active',
        'deactivated_at',
    ];

    protected $hidden = [
        'pin_lookup',
    ];

    protected function casts(): array
    {
        return [
            'document_type'    => IdentityDocumentType::class,
            'pin_generated_at' => 'datetime',
            'is_active'        => 'boolean',
            'deactivated_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
