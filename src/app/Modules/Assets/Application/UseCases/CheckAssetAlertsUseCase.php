<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Enums\AssetDocumentType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Notifications\AssetExpirationAlertNotification;
use App\Modules\Assets\Infrastructure\Notifications\AssetMaintenanceAlertNotification;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetDocumentModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Shared\Application\Services\NotificationRecipientService;

/**
 * Revisión diaria del módulo de activos: mantenimientos vencidos y
 * próximos, garantías y certificados de calibración por vencer. Envía un
 * resumen consolidado a los usuarios con permiso de programar mantenimientos.
 */
class CheckAssetAlertsUseCase
{
    public const MAINTENANCE_DAYS_AHEAD = 15;

    public const EXPIRATION_DAYS_AHEAD = 30;

    public const RECIPIENT_PERMISSION = 'activos_mantenimientos.programar';

    public function __construct(
        private readonly NotificationRecipientService $recipients,
    ) {}

    /** @return array{overdue: int, upcoming: int, warranties: int, certificates: int} */
    public function execute(): array
    {
        $today = now()->startOfDay();
        $limit = $today->copy()->addDays(self::MAINTENANCE_DAYS_AHEAD);

        $open = AssetMaintenanceModel::with('asset')
            ->where('status', MaintenanceStatus::Scheduled->value)
            ->whereDate('scheduled_date', '<=', $limit)
            ->orderBy('scheduled_date')
            ->get();

        $overdue  = $open->filter(fn ($m) => $m->scheduled_date->lt($today));
        $upcoming = $open->filter(fn ($m) => $m->scheduled_date->gte($today));

        $warranties = AssetModel::where('status', '!=', AssetStatus::Retired->value)
            ->whereBetween('warranty_until', [$today->toDateString(), $today->copy()->addDays(self::EXPIRATION_DAYS_AHEAD)->toDateString()])
            ->orderBy('warranty_until')
            ->get();

        $certificates = AssetDocumentModel::with('asset')
            ->where('document_type', AssetDocumentType::CalibrationCertificate->value)
            ->whereBetween('expires_at', [$today->toDateString(), $today->copy()->addDays(self::EXPIRATION_DAYS_AHEAD)->toDateString()])
            ->orderBy('expires_at')
            ->get();

        if ($overdue->isNotEmpty() || $upcoming->isNotEmpty()) {
            $this->recipients->notifyByPermission(self::RECIPIENT_PERMISSION, new AssetMaintenanceAlertNotification(
                overdue:  $this->maintenanceLines($overdue),
                upcoming: $this->maintenanceLines($upcoming),
            ));
        }

        if ($warranties->isNotEmpty() || $certificates->isNotEmpty()) {
            $this->recipients->notifyByPermission(self::RECIPIENT_PERMISSION, new AssetExpirationAlertNotification(
                warranties:   $warranties->map(fn ($a) => "{$a->code} {$a->name} — garantía hasta {$a->warranty_until->format('Y-m-d')}")->values()->all(),
                certificates: $certificates->map(fn ($d) => "{$d->asset?->code} {$d->asset?->name} — {$d->title} vence {$d->expires_at->format('Y-m-d')}")->values()->all(),
            ));
        }

        return [
            'overdue'      => $overdue->count(),
            'upcoming'     => $upcoming->count(),
            'warranties'   => $warranties->count(),
            'certificates' => $certificates->count(),
        ];
    }

    /** @return string[] */
    private function maintenanceLines($maintenances): array
    {
        return $maintenances
            ->map(fn ($m) => "{$m->asset?->code} {$m->asset?->name} — {$m->maintenance_type->label()} ({$m->scheduled_date->format('Y-m-d')})")
            ->values()
            ->all();
    }
}
