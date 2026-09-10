<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Shared\Infrastructure\Notifications\ServiceOrderDiscountApprovedNotification;
use DateTimeImmutable;

class ApproveServiceOrderDiscountUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repo,
    ) {}

    /** @return PatientProcedureRecord[] */
    public function execute(string $orderNumber, int $approverId): array
    {
        $records = $this->repo->findByOrderNumber($orderNumber);

        if (empty($records)) {
            throw new \DomainException("Orden {$orderNumber} no encontrada.");
        }

        $hasPending = collect($records)->some(fn ($r) => $r->getDiscountStatus() === 'pending');

        if (! $hasPending) {
            throw new \DomainException("La orden {$orderNumber} no tiene descuentos pendientes de aprobación.");
        }

        $this->repo->approveDiscountsForOrder($orderNumber, $approverId, new DateTimeImmutable());

        $updated = $this->repo->findByOrderNumber($orderNumber);

        $creatorId = $records[0]->getCreatedByUserId();
        if ($creatorId) {
            $creator  = UserModel::find($creatorId);
            $approver = UserModel::find($approverId);
            $creator?->notify(new ServiceOrderDiscountApprovedNotification(
                $orderNumber,
                $approver?->name ?? 'un aprobador',
            ));
        }

        return $updated;
    }
}
