<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Services\ExpirationAlertService;

class CheckExpirationAlertsUseCase
{
    public function __construct(
        private readonly ExpirationAlertService $expirationService,
    ) {}

    /**
     * @return array{expiring: int, expired: int}
     */
    public function execute(): array
    {
        return $this->expirationService->checkExpiringBatches();
    }
}
