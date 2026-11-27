<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Registro de una solicitud con `Idempotency-Key`. Mientras `response_status`
 * es nulo la solicitud sigue en curso; al terminar guarda la respuesta original.
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string $path
 * @property string $request_hash
 * @property int|null $response_status
 * @property string|null $response_body
 */
class IdempotencyKeyModel extends Model
{
    use Prunable;

    public const RETENTION_HOURS = 24;

    protected $table = 'idempotency_keys';

    protected $fillable = ['user_id', 'key', 'path', 'request_hash', 'response_status', 'response_body'];

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subHours(self::RETENTION_HOURS));
    }
}
