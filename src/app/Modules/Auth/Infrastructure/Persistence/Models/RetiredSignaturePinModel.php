<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PIN de firma liberado que está en cuarentena hasta `available_at`.
 *
 * @property int $id
 * @property string $pin_lookup
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon $retired_at
 * @property \Illuminate\Support\Carbon $available_at
 */
class RetiredSignaturePinModel extends Model
{
    public $timestamps = false;

    protected $table = 'retired_signature_pins';

    protected $fillable = [
        'pin_lookup',
        'user_id',
        'retired_at',
        'available_at',
    ];

    protected $hidden = [
        'pin_lookup',
    ];

    protected function casts(): array
    {
        return [
            'retired_at'   => 'datetime',
            'available_at' => 'datetime',
        ];
    }
}
