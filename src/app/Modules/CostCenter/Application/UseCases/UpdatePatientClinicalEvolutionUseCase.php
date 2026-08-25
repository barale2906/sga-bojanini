<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\PatientClinicalEvolution;
use App\Modules\CostCenter\Domain\Repositories\PatientClinicalEvolutionRepositoryInterface;
use App\Modules\Integration\Infrastructure\Jobs\SyncEvolutionToMedsysJob;

class UpdatePatientClinicalEvolutionUseCase
{
    public function __construct(
        private readonly PatientClinicalEvolutionRepositoryInterface $repository,
    ) {}

    public function execute(int $id, string $content): PatientClinicalEvolution
    {
        $evolution = $this->repository->findById($id);

        if ($evolution === null) {
            throw new \DomainException("Evolución clínica con id {$id} no encontrada.");
        }

        $evolution->updateContent($content);

        $saved = $this->repository->save($evolution);

        SyncEvolutionToMedsysJob::dispatch($saved->getId());

        return $saved;
    }
}
