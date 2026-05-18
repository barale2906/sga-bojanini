<?php

namespace Tests\Unit\Monitoring;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\StatisticalControlService;
use Carbon\Carbon;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class StatisticalControlServiceTest extends TestCase
{
    /**
     * Verifica que los cálculos estadísticos son correctos con datos conocidos.
     *
     * Datos de prueba: [2.0, 4.0, 4.0, 4.0, 5.0, 5.0, 7.0, 9.0]
     * Media esperada: 5.0
     * Varianza: (9+1+1+1+0+0+4+16)/7 = 32/7 ≈ 4.5714
     * σ esperada: √4.5714 ≈ 2.1381
     */
    public function testCalculatesCorrectMeanAndStdDev(): void
    {
        $values = [2.0, 4.0, 4.0, 4.0, 5.0, 5.0, 7.0, 9.0];

        $readings = [];
        $baseDate = new DateTimeImmutable('2026-01-01 08:00:00');
        foreach ($values as $i => $val) {
            $readings[] = new SensorReading(
                id: $i + 1,
                sensorId: 1,
                value: $val,
                readingSource: 'manual',
                recordedAt: $baseDate->modify("+{$i} hours"),
            );
        }

        $mockRepo = $this->createMock(SensorReadingRepositoryInterface::class);
        $mockRepo->method('findBySensorAndDateRange')->willReturn($readings);

        $service = new StatisticalControlService($mockRepo);

        $result = $service->calculateControlChart(
            1,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-02'),
            2.0,
            8.0,
        );

        $this->assertEquals(8, $result['total_readings']);
        $this->assertEqualsWithDelta(5.0, $result['mean'], 0.01);
        $this->assertEqualsWithDelta(2.1381, $result['std_dev'], 0.01);
        $this->assertGreaterThan($result['mean'], $result['ucl']);
        $this->assertLessThan($result['mean'], $result['lcl']);
    }
}
