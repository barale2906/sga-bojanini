<?php

namespace Tests\Unit\Assets;

use App\Modules\Assets\Domain\Enums\FrequencyUnit;
use App\Modules\Assets\Domain\Services\MaintenanceScheduleCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MaintenanceScheduleCalculatorTest extends TestCase
{
    private MaintenanceScheduleCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new MaintenanceScheduleCalculator();
    }

    public function test_suma_dias(): void
    {
        $next = $this->calculator->next(new DateTimeImmutable('2026-01-25'), 10, FrequencyUnit::Days);

        $this->assertSame('2026-02-04', $next->format('Y-m-d'));
    }

    public function test_suma_meses_sin_desbordar_fin_de_mes(): void
    {
        $this->assertSame('2026-02-28', $this->calculator->next(new DateTimeImmutable('2026-01-31'), 1, FrequencyUnit::Months)->format('Y-m-d'));
        $this->assertSame('2028-02-29', $this->calculator->next(new DateTimeImmutable('2027-08-31'), 6, FrequencyUnit::Months)->format('Y-m-d'));
        $this->assertSame('2027-01-15', $this->calculator->next(new DateTimeImmutable('2026-07-15'), 6, FrequencyUnit::Months)->format('Y-m-d'));
    }

    public function test_frecuencia_invalida(): void
    {
        $this->expectException(\DomainException::class);
        $this->calculator->next(new DateTimeImmutable('2026-01-01'), 0, FrequencyUnit::Days);
    }

    public function test_primera_fecha_avanza_hasta_hoy_o_despues(): void
    {
        $today = new DateTimeImmutable('2026-10-17');

        $this->assertSame('2026-10-20', $this->calculator->firstFrom(new DateTimeImmutable('2026-10-20'), 1, FrequencyUnit::Months, $today)->format('Y-m-d'));
        $this->assertSame('2026-11-01', $this->calculator->firstFrom(new DateTimeImmutable('2026-01-01'), 2, FrequencyUnit::Months, $today)->format('Y-m-d'));
        $this->assertSame('2026-10-17', $this->calculator->firstFrom(new DateTimeImmutable('2026-10-07'), 10, FrequencyUnit::Days, $today)->format('Y-m-d'));
    }
}
