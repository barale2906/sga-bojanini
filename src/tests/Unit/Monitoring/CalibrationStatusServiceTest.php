<?php

namespace Tests\Unit\Monitoring;

use App\Modules\Monitoring\Domain\Services\CalibrationStatusService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CalibrationStatusServiceTest extends TestCase
{
    private CalibrationStatusService $service;

    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->service = new CalibrationStatusService();
        $this->today = new DateTimeImmutable('2026-11-16 15:30:00');
    }

    private function in(int $days): DateTimeImmutable
    {
        return $this->today->modify("{$days} days");
    }

    public function test_estado_segun_dias_restantes(): void
    {
        $this->assertSame('missing', $this->service->status(null, $this->today));
        $this->assertSame('valid', $this->service->status($this->in(31), $this->today));
        $this->assertSame('expiring', $this->service->status($this->in(30), $this->today));
        $this->assertSame('expiring', $this->service->status($this->in(0), $this->today));
        $this->assertSame('expired', $this->service->status($this->in(-1), $this->today));
    }

    public function test_dias_restantes_ignora_la_hora(): void
    {
        $this->assertSame(0, $this->service->daysLeft(new DateTimeImmutable('2026-11-16 00:00:00'), $this->today));
        $this->assertSame(-3, $this->service->daysLeft(new DateTimeImmutable('2026-11-13'), $this->today));
    }

    public function test_alerta_solo_en_hitos_y_semanal_una_vez_vencido(): void
    {
        foreach ([30, 15, 7, 3, 1, 0] as $days) {
            $this->assertTrue($this->service->shouldAlert($this->in($days), $this->today), "hito {$days}");
        }

        foreach ([29, 14, 8, 2] as $days) {
            $this->assertFalse($this->service->shouldAlert($this->in($days), $this->today), "no hito {$days}");
        }

        $this->assertTrue($this->service->shouldAlert($this->in(-7), $this->today));
        $this->assertTrue($this->service->shouldAlert($this->in(-14), $this->today));
        $this->assertFalse($this->service->shouldAlert($this->in(-3), $this->today));
    }
}
