<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain\Repositories;

use App\Modules\Integration\Infrastructure\Persistence\Models\ExternalIntegrationModel;

interface ExternalIntegrationRepositoryInterface
{
    /**
     * @return ExternalIntegrationModel[]
     */
    public function findAll(): array;

    public function findById(int $id): ?ExternalIntegrationModel;

    public function save(array $data, ?int $id = null): ExternalIntegrationModel;

    public function delete(int $id): void;
}
