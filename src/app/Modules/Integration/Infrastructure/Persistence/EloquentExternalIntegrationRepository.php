<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Persistence;

use App\Modules\Integration\Domain\Repositories\ExternalIntegrationRepositoryInterface;
use App\Modules\Integration\Infrastructure\Persistence\Models\ExternalIntegrationModel;

class EloquentExternalIntegrationRepository implements ExternalIntegrationRepositoryInterface
{
    public function findAll(): array
    {
        return ExternalIntegrationModel::orderBy('name')->get()->all();
    }

    public function findById(int $id): ?ExternalIntegrationModel
    {
        return ExternalIntegrationModel::find($id);
    }

    public function save(array $data, ?int $id = null): ExternalIntegrationModel
    {
        $model = $id ? ExternalIntegrationModel::findOrFail($id) : new ExternalIntegrationModel();

        $model->fill([
            'name'      => $data['name'],
            'type'      => $data['type'],
            'base_url'  => $data['base_url'],
            'auth_type' => $data['auth_type'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $model->credentials = encrypt(json_encode($data['credentials']));
        }

        $model->save();

        return $model;
    }

    public function delete(int $id): void
    {
        ExternalIntegrationModel::findOrFail($id)->delete();
    }
}
