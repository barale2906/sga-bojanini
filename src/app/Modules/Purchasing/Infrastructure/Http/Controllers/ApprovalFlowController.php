<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Controllers;

use App\Modules\Purchasing\Application\UseCases\CreateApprovalFlowUseCase;
use App\Modules\Purchasing\Application\UseCases\ListApprovalFlowsUseCase;
use App\Modules\Purchasing\Application\UseCases\UpdateApprovalFlowUseCase;
use App\Modules\Purchasing\Infrastructure\Http\Requests\StoreApprovalFlowRequest;
use App\Modules\Purchasing\Infrastructure\Http\Resources\ApprovalFlowResource;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApprovalFlowController extends Controller
{
    use ApiResponse;

    public function index(Request $request, ListApprovalFlowsUseCase $useCase): JsonResponse
    {
        $flows = $useCase->execute($request->query('entity_type'));

        return $this->success(
            ApprovalFlowResource::collection($flows),
            'Listado de flujos de aprobación',
        );
    }

    public function store(StoreApprovalFlowRequest $request, CreateApprovalFlowUseCase $useCase): JsonResponse
    {
        $flow = $useCase->execute($request->validated());

        return $this->created(new ApprovalFlowResource($flow), 'Flujo de aprobación creado');
    }

    public function show(int $id): JsonResponse
    {
        $flow = ApprovalFlowModel::with('steps.role')->findOrFail($id);

        return $this->success(new ApprovalFlowResource($flow), 'Detalle del flujo de aprobación');
    }

    public function update(int $id, StoreApprovalFlowRequest $request, UpdateApprovalFlowUseCase $useCase): JsonResponse
    {
        $flow = $useCase->execute($id, $request->validated());

        return $this->success(new ApprovalFlowResource($flow), 'Flujo de aprobación actualizado');
    }

    public function destroy(int $id): JsonResponse
    {
        $flow = ApprovalFlowModel::findOrFail($id);
        $flow->delete();

        return $this->noContent('Flujo de aprobación eliminado');
    }
}
