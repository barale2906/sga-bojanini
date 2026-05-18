<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Entities;

class AlertRule
{
    public function __construct(
        private ?int $id,
        private int $sensorId,
        private string $conditionType,
        private ?float $threshold,
        private int $consecutiveReadings = 1,
        private array $notificationChannels = ['internal'],
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSensorId(): int
    {
        return $this->sensorId;
    }

    public function getConditionType(): string
    {
        return $this->conditionType;
    }

    public function getThreshold(): ?float
    {
        return $this->threshold;
    }

    public function getConsecutiveReadings(): int
    {
        return $this->consecutiveReadings;
    }

    public function getNotificationChannels(): array
    {
        return $this->notificationChannels;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isViolatedBy(float $value): bool
    {
        return match ($this->conditionType) {
            'above'        => $this->threshold !== null && $value > $this->threshold,
            'below'        => $this->threshold !== null && $value < $this->threshold,
            'out_of_range' => false,
            default        => false,
        };
    }
}
