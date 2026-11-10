<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use Carbon\Carbon;

/**
 * Política de fecha de movimientos: la fecha puede ser retroactiva, pero la
 * hora siempre es la del momento en que se registra (nunca medianoche).
 */
final class MovementTimestamp
{
    public static function resolve(mixed $date = null): Carbon
    {
        $now = Carbon::now();

        if ($date === null || $date === '') {
            return $now;
        }

        // Se toma solo el día calendario tal cual llega (sin convertir zonas
        // horarias, que correrían la fecha un día) y se le aplica la hora actual.
        if (is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            $parsed = Carbon::parse($date);
            [$y, $mo, $d] = [$parsed->year, $parsed->month, $parsed->day];
        }

        return $now->copy()->setDate($y, $mo, $d);
    }
}
