<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Repositories;

use App\Modules\Monitoring\Domain\Entities\Sensor;

interface SensorRepositoryInterface
{
    public function findById(int $id): ?Sensor;

    public function findByCode(string $code): ?Sensor;

    /**
     * @return Sensor[]
     */
    public function findAll(array $filters = []): array;

    /**
     * @return Sensor[]
     */
    public function findAllActive(): array;

    public function save(Sensor $sensor): Sensor;

    public function delete(int $id): void;
}
