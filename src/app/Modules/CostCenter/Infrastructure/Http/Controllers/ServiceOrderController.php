<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\ServiceOrderData;
use App\Modules\CostCenter\Application\DTOs\ServiceOrderLineData;
use App\Modules\CostCenter\Application\UseCases\ApproveServiceOrderDiscountUseCase;
use App\Modules\CostCenter\Application\UseCases\CreateServiceOrderUseCase;
use App\Modules\CostCenter\Application\UseCases\GetServiceOrderUseCase;
use App\Modules\CostCenter\Application\UseCases\ListPendingDiscountOrdersUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdateOrderBillingStatusUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StoreServiceOrderRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\PatientProcedureRecordResource;
use App\Modules\CostCenter\Infrastructure\Http\Resources\ServiceOrderResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * @group Órdenes de Servicio
 *
 * Registro y gestión de procedimientos médicos agrupados por orden.
 */
class ServiceOrderController extends Controller
{
    use ApiResponse;

    /**
     * Crear una orden de servicio con uno o más procedimientos.
     *
     * @response 201 {"success":true,"data":{"order_number":"OS-20260909-000001",...}}
     */
    public function store(StoreServiceOrderRequest $request, CreateServiceOrderUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $lines = array_map(fn ($p) => new ServiceOrderLineData(
            medicalServiceId: (int) $p['medical_service_id'],
            unitPrice:        (float) $p['unit_price'],
            quantity:         (float) $p['quantity'],
            discountType:     $p['discount_type'] ?? null,
            discountValue:    isset($p['discount_value']) ? (float) $p['discount_value'] : null,
            notes:            $p['notes'] ?? null,
            supplies:         $p['supplies'] ?? [],
        ), $validated['procedures']);

        $data = new ServiceOrderData(
            patientExternalId: $validated['patient_external_id'],
            patientDocument:   $validated['patient_document'],
            patientFirstName:  $validated['patient_first_name'],
            patientLastName:   $validated['patient_last_name'],
            serviceDate:       new DateTimeImmutable($validated['service_date']),
            procedures:        $lines,
            notes:             $validated['notes'] ?? null,
            patientEmail:      $validated['patient_email'] ?? null,
            patientAddress:    $validated['patient_address'] ?? null,
            patientPhone:      $validated['patient_phone'] ?? null,
            seller:            $validated['seller'] ?? null,
            referrer:          $validated['referrer'] ?? null,
        );

        $records = $useCase->execute($data, (int) auth()->id());
        $first   = $records[0];

        return $this->created(
            new ServiceOrderResource([
                'order_number'        => $first->getOrderNumber(),
                'patient_external_id' => $first->getPatientExternalId(),
                'patient_document'    => $first->getPatientDocument(),
                'patient_first_name'  => $first->getPatientFirstName(),
                'patient_last_name'   => $first->getPatientLastName(),
                'patient_email'       => $first->getPatientEmail(),
                'patient_address'     => $first->getPatientAddress(),
                'patient_phone'       => $first->getPatientPhone(),
                'service_date'        => $first->getServiceDate()->format('Y-m-d'),
                'created_by_user_id'  => $first->getCreatedByUserId(),
                'records'             => $records,
            ]),
            'Orden de servicio creada exitosamente',
        );
    }

    /**
     * Obtener una orden de servicio por su número.
     *
     * @urlParam orderNumber string required Número de orden. Example: OS-20260909-000001
     */
    public function show(string $orderNumber, GetServiceOrderUseCase $useCase): JsonResponse
    {
        $records = $useCase->execute($orderNumber);
        $first   = $records[0];

        return $this->success(new ServiceOrderResource([
            'order_number'        => $orderNumber,
            'patient_external_id' => $first->getPatientExternalId(),
            'patient_document'    => $first->getPatientDocument(),
            'patient_first_name'  => $first->getPatientFirstName(),
            'patient_last_name'   => $first->getPatientLastName(),
            'patient_email'       => $first->getPatientEmail(),
            'patient_address'     => $first->getPatientAddress(),
            'patient_phone'       => $first->getPatientPhone(),
            'service_date'        => $first->getServiceDate()->format('Y-m-d'),
            'created_by_user_id'  => $first->getCreatedByUserId(),
            'records'             => $records,
        ]), "Orden {$orderNumber}");
    }

    /**
     * Listar órdenes con descuentos pendientes de aprobación.
     */
    public function discounts(ListPendingDiscountOrdersUseCase $useCase): JsonResponse
    {
        $orders = $useCase->execute();

        $result = array_map(function (array $order) {
            $order['records'] = array_map(
                fn ($record) => (new PatientProcedureRecordResource($record))->toArray(request()),
                $order['records']
            );
            return $order;
        }, $orders);

        return $this->success($result, 'Órdenes con descuentos pendientes');
    }

    /**
     * Marcar una orden como facturada.
     *
     * @urlParam orderNumber string required Número de orden. Example: OS-20260909-000001
     * @response 200 {"success":true,"data":{"order_number":"OS-20260909-000001","billing_status":"billed",...}}
     * @response 409 {"success":false,"message":"La orden ... ya se encuentra en estado 'billed'."}
     */
    public function bill(string $orderNumber, UpdateOrderBillingStatusUseCase $useCase): JsonResponse
    {
        $records = $useCase->execute($orderNumber, 'billed', (int) auth()->id());
        $first   = $records[0];

        return $this->success(new ServiceOrderResource([
            'order_number'        => $orderNumber,
            'patient_external_id' => $first->getPatientExternalId(),
            'patient_document'    => $first->getPatientDocument(),
            'patient_first_name'  => $first->getPatientFirstName(),
            'patient_last_name'   => $first->getPatientLastName(),
            'patient_email'       => $first->getPatientEmail(),
            'patient_address'     => $first->getPatientAddress(),
            'patient_phone'       => $first->getPatientPhone(),
            'service_date'        => $first->getServiceDate()->format('Y-m-d'),
            'created_by_user_id'  => $first->getCreatedByUserId(),
            'records'             => $records,
        ]), 'Orden marcada como facturada');
    }

    /**
     * Anular una orden de servicio.
     *
     * @urlParam orderNumber string required Número de orden. Example: OS-20260909-000001
     * @response 200 {"success":true,"data":{"order_number":"OS-20260909-000001","billing_status":"cancelled",...}}
     * @response 409 {"success":false,"message":"La orden ... ya se encuentra en estado 'cancelled'."}
     */
    public function cancel(string $orderNumber, UpdateOrderBillingStatusUseCase $useCase): JsonResponse
    {
        $records = $useCase->execute($orderNumber, 'cancelled', (int) auth()->id());
        $first   = $records[0];

        return $this->success(new ServiceOrderResource([
            'order_number'        => $orderNumber,
            'patient_external_id' => $first->getPatientExternalId(),
            'patient_document'    => $first->getPatientDocument(),
            'patient_first_name'  => $first->getPatientFirstName(),
            'patient_last_name'   => $first->getPatientLastName(),
            'patient_email'       => $first->getPatientEmail(),
            'patient_address'     => $first->getPatientAddress(),
            'patient_phone'       => $first->getPatientPhone(),
            'service_date'        => $first->getServiceDate()->format('Y-m-d'),
            'created_by_user_id'  => $first->getCreatedByUserId(),
            'records'             => $records,
        ]), 'Orden anulada exitosamente');
    }

    /**
     * Aprobar el descuento de una orden.
     *
     * @urlParam orderNumber string required Número de orden. Example: OS-20260909-000001
     */
    public function approve(string $orderNumber, ApproveServiceOrderDiscountUseCase $useCase): JsonResponse
    {
        $records = $useCase->execute($orderNumber, (int) auth()->id());
        $first   = $records[0];

        return $this->success(new ServiceOrderResource([
            'order_number'        => $orderNumber,
            'patient_external_id' => $first->getPatientExternalId(),
            'patient_document'    => $first->getPatientDocument(),
            'patient_first_name'  => $first->getPatientFirstName(),
            'patient_last_name'   => $first->getPatientLastName(),
            'patient_email'       => $first->getPatientEmail(),
            'patient_address'     => $first->getPatientAddress(),
            'patient_phone'       => $first->getPatientPhone(),
            'service_date'        => $first->getServiceDate()->format('Y-m-d'),
            'created_by_user_id'  => $first->getCreatedByUserId(),
            'records'             => $records,
        ]), 'Descuento aprobado exitosamente');
    }
}
