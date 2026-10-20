<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Services;

use App\Modules\Assets\Domain\Enums\FrequencyUnit;
use DateTimeImmutable;

/**
 * Cálculo de fechas del cronograma de mantenimiento.
 */
class MaintenanceScheduleCalculator
{
    /**
     * Próxima fecha a partir de una fecha base. Con meses se respeta el fin
     * de mes (31-ene + 1 mes = 28/29-feb), sin desbordar al mes siguiente.
     */
    public function next(DateTimeImmutable $base, int $value, FrequencyUnit $unit): DateTimeImmutable
    {
        if ($value < 1) {
            throw new \DomainException('La frecuencia debe ser mayor a cero.');
        }

        if ($unit === FrequencyUnit::Days) {
            return $base->modify("+{$value} days");
        }

        $firstOfTarget = $base->modify('first day of this month')->modify("+{$value} months");
        $day           = min((int) $base->format('j'), (int) $firstOfTarget->format('t'));

        return $firstOfTarget->setDate(
            (int) $firstOfTarget->format('Y'),
            (int) $firstOfTarget->format('n'),
            $day,
        );
    }

    /**
     * Primera fecha programable de un plan: si `start` ya pasó, avanza por
     * la frecuencia hasta la primera fecha >= hoy.
     */
    public function firstFrom(DateTimeImmutable $start, int $value, FrequencyUnit $unit, DateTimeImmutable $today): DateTimeImmutable
    {
        $date = $start;

        while ($date < $today) {
            $date = $this->next($date, $value, $unit);
        }

        return $date;
    }
}
