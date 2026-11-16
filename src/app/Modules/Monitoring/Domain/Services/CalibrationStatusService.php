<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Services;

use DateTimeInterface;

/**
 * Reglas de vigencia de los certificados de calibración de los equipos.
 *
 * Estados:
 *   - missing  → el equipo no tiene certificado cargado.
 *   - valid    → vigente y con más de EXPIRING_DAYS días por delante.
 *   - expiring → vence dentro de EXPIRING_DAYS días (incluye "vence hoy").
 *   - expired  → la fecha de vencimiento ya pasó.
 *
 * Las alertas no se envían todos los días para no saturar a los usuarios:
 * se envían en hitos (30, 15, 7, 3, 1 y 0 días antes) y, una vez vencido,
 * cada OVERDUE_REMINDER_DAYS días hasta que se cargue el reemplazo.
 */
class CalibrationStatusService
{
    public const EXPIRING_DAYS = 30;

    /** Días antes del vencimiento en los que se envía alerta. */
    public const ALERT_MILESTONES = [30, 15, 7, 3, 1, 0];

    public const OVERDUE_REMINDER_DAYS = 7;

    public const STATUS_MISSING = 'missing';
    public const STATUS_VALID = 'valid';
    public const STATUS_EXPIRING = 'expiring';
    public const STATUS_EXPIRED = 'expired';

    /** Días que faltan para el vencimiento (negativo si ya venció). */
    public function daysLeft(DateTimeInterface $expiresAt, DateTimeInterface $today): int
    {
        $expires = new \DateTimeImmutable($expiresAt->format('Y-m-d'));
        $base = new \DateTimeImmutable($today->format('Y-m-d'));

        return (int) $base->diff($expires)->format('%r%a');
    }

    public function status(?DateTimeInterface $expiresAt, DateTimeInterface $today): string
    {
        if ($expiresAt === null) {
            return self::STATUS_MISSING;
        }

        $days = $this->daysLeft($expiresAt, $today);

        return match (true) {
            $days < 0                    => self::STATUS_EXPIRED,
            $days <= self::EXPIRING_DAYS => self::STATUS_EXPIRING,
            default                      => self::STATUS_VALID,
        };
    }

    /** Indica si hoy corresponde enviar alerta para un certificado vigente con esa fecha. */
    public function shouldAlert(DateTimeInterface $expiresAt, DateTimeInterface $today): bool
    {
        $days = $this->daysLeft($expiresAt, $today);

        if ($days >= 0) {
            return in_array($days, self::ALERT_MILESTONES, true);
        }

        return abs($days) % self::OVERDUE_REMINDER_DAYS === 0;
    }

    public function label(string $status): string
    {
        return match ($status) {
            self::STATUS_MISSING  => 'Sin certificado',
            self::STATUS_VALID    => 'Vigente',
            self::STATUS_EXPIRING => 'Por vencer',
            self::STATUS_EXPIRED  => 'Vencido',
            default               => $status,
        };
    }
}
