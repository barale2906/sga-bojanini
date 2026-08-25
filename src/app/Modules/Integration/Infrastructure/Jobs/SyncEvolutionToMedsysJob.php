<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Jobs;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientClinicalEvolutionModel;
use App\Modules\Integration\Domain\Ports\MedsysEvolutionServiceInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEvolutionToMedsysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 20, 40];

    public function __construct(private readonly int $evolutionId) {}

    public function handle(MedsysEvolutionServiceInterface $medsysService): void
    {
        if (! $medsysService->isEnabled()) {
            return;
        }

        $evolution = PatientClinicalEvolutionModel::with([
            'procedureRecord',
            'user',
        ])->findOrFail($this->evolutionId);

        try {
            $medsysService->pushEvolution(
                medsysPatientId: $evolution->procedureRecord->patient_external_id,
                controlCode:     '',
                date:            $evolution->recorded_at->toDateString(),
                time:            $evolution->recorded_at->format('H:i:s'),
                evolutionText:   $this->buildEvolutionText($evolution),
                user:            $evolution->user?->name ?? 'SGA',
                evolutionId:     $this->evolutionId,
            );
        } catch (\Throwable $e) {
            Log::error("MedSys: error al sincronizar evolución #{$this->evolutionId}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function buildEvolutionText(PatientClinicalEvolutionModel $evolution): string
    {
        $text               = $evolution->content;
        $movementDocumentId = $evolution->procedureRecord?->movement_document_id;

        if ($movementDocumentId === null) {
            return $text;
        }

        $injectables = StockMovementModel::with(['variant.genericProduct.classification', 'batch'])
            ->where('movement_document_id', $movementDocumentId)
            ->get()
            ->filter(fn (StockMovementModel $m) =>
                $m->variant?->genericProduct?->classification?->code === 'MED'
                && str_contains(
                    strtolower($m->variant?->genericProduct?->pharmaceutical_form ?? ''),
                    'inyect'
                )
            );

        if ($injectables->isEmpty()) {
            return $text;
        }

        $text .= "\n\nMedicamentos inyectables aplicados:";
        foreach ($injectables as $mov) {
            $text .= sprintf(
                "\n- %s | Lote: %s | Vence: %s | Cant: %s",
                $mov->variant?->genericProduct?->name ?? 'N/A',
                $mov->batch?->lot_number ?? 'N/A',
                $mov->batch?->expiration_date?->format('Y-m-d') ?? 'N/A',
                abs((float) $mov->quantity),
            );
        }

        return $text;
    }
}
