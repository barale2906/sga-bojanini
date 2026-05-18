<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;

class DeleteAlertRuleUseCase
{
    public function __construct(
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->alertRuleRepository->findById($id) === null) {
            throw new \DomainException('Regla de alerta no encontrada.');
        }

        $this->alertRuleRepository->delete($id);
    }
}
