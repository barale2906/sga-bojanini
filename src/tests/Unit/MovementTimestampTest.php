<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Inventory\Application\Services\MovementTimestamp;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MovementTimestampTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function test_past_date_keeps_day_and_uses_current_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:41:27'));

        foreach (['2026-10-29', '2026-10-29T00:00:00.000Z', '2026-10-29 00:00:00'] as $input) {
            $this->assertSame('2026-10-29 09:41:27', MovementTimestamp::resolve($input)->format('Y-m-d H:i:s'));
        }
    }

    public function test_null_uses_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:41:27'));
        $this->assertSame('2026-11-10 09:41:27', MovementTimestamp::resolve(null)->format('Y-m-d H:i:s'));
    }
}
