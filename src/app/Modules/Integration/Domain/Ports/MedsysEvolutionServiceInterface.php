<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain\Ports;

interface MedsysEvolutionServiceInterface
{
    public function isEnabled(): bool;

    public function pushEvolution(
        string $medsysPatientId,
        string $controlCode,
        string $date,
        string $time,
        string $evolutionText,
        string $user,
        int    $evolutionId,
    ): void;
}
