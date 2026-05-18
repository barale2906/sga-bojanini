<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Traits;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Trait que registra automáticamente create, update y delete en audit_logs.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            self::writeAuditLog(
                action: 'create',
                model: $model,
                oldValues: null,
                newValues: $model->getAttributes(),
            );
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();

            if (empty($changes)) {
                return;
            }

            $oldValues = [];
            foreach (array_keys($changes) as $field) {
                if (in_array($field, ['updated_at', 'created_at'], true)) {
                    continue;
                }
                $oldValues[$field] = $model->getOriginal($field);
            }

            $newValues = array_diff_key($changes, array_flip(['updated_at', 'created_at']));

            if (!empty($newValues)) {
                self::writeAuditLog(
                    action: 'update',
                    model: $model,
                    oldValues: $oldValues,
                    newValues: $newValues,
                );
            }
        });

        static::deleted(function ($model) {
            self::writeAuditLog(
                action: 'delete',
                model: $model,
                oldValues: $model->getAttributes(),
                newValues: null,
            );
        });
    }

    private static function writeAuditLog(
        string $action,
        mixed $model,
        ?array $oldValues,
        ?array $newValues,
    ): void {
        try {
            $className = class_basename($model);
            $auditableType = str_replace('Model', '', $className);
            $auditableType = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $auditableType));

            AuditLogModel::create([
                'user_id'        => Auth::id(),
                'action'         => $action,
                'auditable_type' => $auditableType,
                'auditable_id'   => $model->getKey(),
                'old_values'     => $oldValues,
                'new_values'     => $newValues,
                'ip_address'     => request()?->ip(),
                'user_agent'     => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar auditoría: '.$e->getMessage(), [
                'action' => $action,
                'model'  => get_class($model),
                'id'     => $model->getKey(),
            ]);
        }
    }
}
