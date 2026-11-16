<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Domain\Services\CalibrationStatusService;
use App\Modules\Monitoring\Infrastructure\Notifications\SensorCalibrationAlertNotification;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Revisión diaria de los certificados de calibración vigentes de los
 * equipos activos. En los días de hito (ver CalibrationStatusService)
 * notifica a:
 *   - los roles configurados en sga.notification_recipients.sensor_calibration_alert
 *     (más super_administrador), y
 *   - los usuarios asignados a cada equipo (derivados de su almacén).
 *
 * Cada usuario recibe una sola notificación con los equipos que le
 * corresponden.
 */
class CheckSensorCalibrationsUseCase
{
    public const NOTIFICATION_TYPE = 'sensor_calibration_alert';

    public function __construct(
        private readonly CalibrationStatusService $statusService,
    ) {}

    /** @return array{expiring: int, expired: int, notified_users: int} */
    public function execute(?CarbonInterface $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        $due = SensorCalibrationCertificateModel::with('sensor.zone.warehouse')
            ->where('is_current', true)
            ->whereHas('sensor', fn ($q) => $q->where('is_active', true))
            ->whereDate('expires_at', '<=', $today->copy()->addDays(CalibrationStatusService::EXPIRING_DAYS))
            ->orderBy('expires_at')
            ->get()
            ->filter(fn (SensorCalibrationCertificateModel $c) => $this->statusService->shouldAlert($c->expires_at, $today))
            ->values();

        if ($due->isEmpty()) {
            return ['expiring' => 0, 'expired' => 0, 'notified_users' => 0];
        }

        $roleUserIds = $this->roleRecipientIds();

        $sensorUsers = DB::table('user_sensor')
            ->whereIn('sensor_id', $due->pluck('sensor_id')->unique())
            ->get(['user_id', 'sensor_id'])
            ->groupBy('sensor_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->all());

        /** @var array<int, array{expiring: string[], expired: string[]}> $perUser */
        $perUser = [];
        $expiringCount = 0;
        $expiredCount = 0;

        foreach ($due as $certificate) {
            $days = $this->statusService->daysLeft($certificate->expires_at, $today);
            $bucket = $days < 0 ? 'expired' : 'expiring';
            $days < 0 ? $expiredCount++ : $expiringCount++;

            $line = $this->line($certificate, $days);
            $recipients = array_unique(array_merge($roleUserIds, $sensorUsers->get($certificate->sensor_id, [])));

            foreach ($recipients as $userId) {
                $perUser[$userId] ??= ['expiring' => [], 'expired' => []];
                $perUser[$userId][$bucket][] = $line;
            }
        }

        $users = UserModel::whereIn('id', array_keys($perUser))->where('is_active', true)->get();

        foreach ($users as $user) {
            $user->notify(new SensorCalibrationAlertNotification(
                expiring: $perUser[$user->id]['expiring'],
                expired:  $perUser[$user->id]['expired'],
            ));
        }

        return ['expiring' => $expiringCount, 'expired' => $expiredCount, 'notified_users' => $users->count()];
    }

    /** @return int[] */
    private function roleRecipientIds(): array
    {
        $roles = config('sga.notification_recipients.' . self::NOTIFICATION_TYPE, []);
        $roles = array_unique(array_merge($roles, ['super_administrador']));

        return UserModel::role($roles)->pluck('id')->all();
    }

    private function line(SensorCalibrationCertificateModel $certificate, int $days): string
    {
        $sensor = $certificate->sensor;
        $place = trim(($sensor?->zone?->warehouse?->name ?? '') . ' / ' . ($sensor?->zone?->name ?? ''), ' /');
        $date = $certificate->expires_at->format('Y-m-d');

        $when = match (true) {
            $days < 0   => "venció el {$date} (hace " . abs($days) . ' día(s))',
            $days === 0 => "vence HOY ({$date})",
            default     => "vence el {$date} (en {$days} día(s))",
        };

        return "{$sensor?->name} [{$place}] — {$when}";
    }
}
