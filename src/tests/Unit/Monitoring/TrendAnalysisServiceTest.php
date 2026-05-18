<?php

namespace Tests\Unit\Monitoring;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\TrendAnalysisService;
use Carbon\Carbon;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TrendAnalysisServiceTest extends TestCase
{
    public function testDetectsUpwardTrend(): void
    {
        // 10 lecturas consecutivas crecientes → debe detectar tendencia ascendente
        $values = [4.0, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9];

        $readings = $this->buildReadings($values);
        $service  = $this->buildService($readings);

        $result = $service->analyzeTrend(1, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-02'));

        $this->assertTrue($result['overall_trend']);
        $this->assertEquals('up', $result['overall_direction']);
    }

    public function testDetectsNoTrend(): void
    {
        // Lecturas aleatorias sin patrón claro
        $values = [4.5, 4.2, 4.8, 4.1, 4.6, 4.3, 4.7, 4.0, 4.9, 4.4];

        $readings = $this->buildReadings($values);
        $service  = $this->buildService($readings);

        $result = $service->analyzeTrend(1, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-02'));

        $this->assertFalse($result['overall_trend']);
        $this->assertNull($result['overall_direction']);
    }

    private function buildReadings(array $values): array
    {
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
        return $readings;
    }

    private function buildService(array $readings): TrendAnalysisService
    {
        $mockRepo = $this->createMock(SensorReadingRepositoryInterface::class);
        $mockRepo->method('findBySensorAndDateRange')->willReturn($readings);

        return new TrendAnalysisService($mockRepo);
    }
}
