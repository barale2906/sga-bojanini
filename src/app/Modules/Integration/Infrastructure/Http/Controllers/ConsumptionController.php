<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Controllers;

use App\Modules\Integration\Application\UseCases\RegisterConsumptionUseCase;
use App\Modules\Integration\Domain\Repositories\ConsumptionRecordRepositoryInterface;
use App\Modules\Integration\Infrastructure\Http\Requests\StoreConsumptionRequest;
use App\Modules\Integration\Infrastructure\Http\Resources\ConsumptionRecordResource;
use App\Modules\Integration\Infrastructure\Jobs\SyncConsumptionToHCJob;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConsumptionController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request, ConsumptionRecordRepositoryInterface $repository): JsonResponse
    {
        $result = $repository->findAll([
            'sync_status'        => $request->query('sync_status'),
            'appointment_id'     => $request->query('appointment_id'),
            'patient_identifier' => $request->query('patient_identifier'),
            'date_from'          => $request->query('date_from'),
            'date_to'            => $request->query('date_to'),
            'page'               => $request->query('page'),
            'per_page'           => $request->query('per_page'),
        ]);

        return $this->success([
            'items' => ConsumptionRecordResource::collection($result['data']),
            'meta'  => $result['meta'],
        ], 'Listado de consumos');
    }

    public function store(StoreConsumptionRequest $request, RegisterConsumptionUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $result = $useCase->execute($request->validated());

        return $this->created([
            'records'     => ConsumptionRecordResource::collection($result['records']),
            'total_items' => $result['total_items'],
        ], 'Consumo registrado correctamente');
    }

    public function show(int $id, ConsumptionRecordRepositoryInterface $repository): JsonResponse
    {
        $record = $repository->findById($id);

        if ($record === null) {
            return $this->error('Registro de consumo no encontrado', 404);
        }

        return $this->success(new ConsumptionRecordResource($record));
    }

    public function retrySync(int $id): JsonResponse
    {
        $record = ConsumptionRecordModel::find($id);

        if ($record === null) {
            return $this->error('Registro de consumo no encontrado', 404);
        }

        if ($record->sync_status !== 'failed') {
            return $this->error('Solo se puede reintentar sincronización de registros fallidos', 409);
        }

        if (empty($record->appointment_id) || empty($record->patient_identifier)) {
            return $this->error('El registro no tiene datos de cita o paciente para sincronizar', 409);
        }

        $record->update(['sync_status' => 'pending']);

        SyncConsumptionToHCJob::dispatch(
            $record->patient_identifier,
            $record->appointment_id,
            [$record->id],
        );

        return $this->success(null, 'Sincronización reencolada');
    }
}
