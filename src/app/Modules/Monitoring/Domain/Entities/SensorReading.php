<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Entities;

use DateTimeImmutable;

class SensorReading
{
    public function __construct(
        private ?int $id,
        private int $sensorId,
        private float $value,
        private string $readingSource,
        private DateTimeImmutable $recordedAt,
        private ?int $userId = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSensorId(): int
    {
        return $this->sensorId;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getReadingSource(): string
    {
        return $this->readingSource;
    }

    public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function isWithinRange(float $min, float $max): bool
    {
        return $this->value >= $min && $this->value <= $max;
    }
}
