<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\ServiceOrderData;
use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use App\Modules\Inventory\Application\UseCases\RegisterExitUseCase;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Shared\Infrastructure\Notifications\ServiceOrderDiscountPendingNotification;

class CreateServiceOrderUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $recordRepo,
        private readonly MedicalServiceRepositoryInterface $serviceRepo,
        private readonly RegisterExitUseCase $registerExitUseCase,
    ) {}

    /** @return PatientProcedureRecord[] */
    public function execute(ServiceOrderData $data, int $createdByUserId): array
    {
        $orderNumber = $this->generateOrderNumber();

        $records = [];
        foreach ($data->procedures as $line) {
            $service = $this->serviceRepo->findById($line->medicalServiceId);
            if ($service === null || $service->getType() !== MedicalServiceType::PROCEDURE) {
                throw new \DomainException("El servicio {$line->medicalServiceId} no es un procedimiento.");
            }

            $total          = PatientProcedureRecord::calculateTotal($line->quantity, $line->unitPrice);
            $discountAmount = PatientProcedureRecord::calculateDiscountAmount($total, $line->discountType, $line->discountValue);
            $netTotal       = round($total - $discountAmount, 2);
            $discountStatus = ($line->discountType !== null) ? 'pending' : null;

            $movementDocumentId = $line->movementDocumentId;
            if (! empty($line->supplies)) {
                $doc = $this->registerExitUseCase->execute([
                    'warehouse_id'        => $line->supplies[0]['warehouse_id'],
                    'movement_date'       => $data->serviceDate->format('Y-m-d'),
                    'cost_center_id'      => null,
                    'patient_document'    => $data->patientDocument,
                    'patient_external_id' => $data->patientExternalId,
                    'user_id'             => $createdByUserId,
                    'reason'              => "Orden de servicio {$orderNumber}",
                    'items'               => array_map(fn ($s) => [
                        'generic_product_id' => $s['generic_product_id'],
                        'quantity'           => $s['quantity'],
                    ], $line->supplies),
                ]);
                $movementDocumentId = $doc->id;
            }

            $record = PatientProcedureRecord::create(
                medicalServiceId:  $line->medicalServiceId,
                patientExternalId: $data->patientExternalId,
                patientDocument:   $data->patientDocument,
                patientFirstName:  $data->patientFirstName,
                patientLastName:   $data->patientLastName,
                quantity:          $line->quantity,
                unitPrice:         $line->unitPrice,
                serviceDate:       $data->serviceDate,
                notes:             $line->notes ?? $data->notes,
                seller:            $data->seller,
                referrer:          $data->referrer,
                movementDocumentId: $movementDocumentId,
                orderNumber:       $orderNumber,
                discountType:      $line->discountType,
                discountValue:     $line->discountValue,
                discountAmount:    $discountAmount > 0 ? $discountAmount : null,
                netTotal:          $discountAmount > 0 ? $netTotal : null,
                discountStatus:    $discountStatus,
                createdByUserId:   $createdByUserId,
                patientEmail:      $data->patientEmail,
                patientAddress:    $data->patientAddress,
                patientPhone:      $data->patientPhone,
            );

            $records[] = $this->recordRepo->save($record);
        }

        $hasDiscounts = collect($records)->some(fn ($r) => $r->getDiscountStatus() === 'pending');

        if ($hasDiscounts) {
            $totalDiscount = collect($records)->sum(fn ($r) => $r->getDiscountAmount() ?? 0);
            $patientName   = $records[0]->getPatientFirstName() . ' ' . $records[0]->getPatientLastName();

            $approvers = UserModel::permission('ordenes_servicio.aprobar')->get();
            foreach ($approvers as $approver) {
                $approver->notify(new ServiceOrderDiscountPendingNotification(
                    $records[0]->getOrderNumber(),
                    $patientName,
                    (float) $totalDiscount,
                ));
            }
        }

        return $records;
    }

    private function generateOrderNumber(): string
    {
        $date  = now()->format('Ymd');
        $count = PatientProcedureRecordModel::whereDate('created_at', today())
            ->whereNotNull('order_number')
            ->count();

        return 'OS-' . $date . '-' . str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
