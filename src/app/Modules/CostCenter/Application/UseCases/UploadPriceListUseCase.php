<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UploadPriceListUseCase
{
    public function __construct(
        private readonly ProcedurePriceRepositoryInterface $repo,
    ) {}

    public function execute(string $filePath, int $userId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $prices    = [];
        $skipped   = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            if ($rowNumber === 1) {
                $rowNumber++;
                continue;
            }

            $code  = trim((string) ($row['A'] ?? ''));
            $price = $row['D'] ?? null;

            if ($code === '') {
                $rowNumber++;
                continue;
            }

            $service = MedicalServiceModel::where('code', $code)->first();
            if ($service === null) {
                $skipped[] = ['row' => $rowNumber, 'code' => $code, 'reason' => 'Código no encontrado'];
                $rowNumber++;
                continue;
            }

            if ($price === null || ! is_numeric($price) || (float) $price < 0) {
                $skipped[] = ['row' => $rowNumber, 'code' => $code, 'reason' => 'Precio inválido'];
                $rowNumber++;
                continue;
            }

            $prices[] = [
                'medical_service_id' => $service->id,
                'unit_price'         => (float) $price,
                'loaded_by_user_id'  => $userId,
            ];
            $rowNumber++;
        }

        if (! empty($skipped) && empty($prices)) {
            throw new \DomainException('El archivo no contiene precios válidos.');
        }

        DB::transaction(function () use ($prices) {
            $this->repo->deactivateAllActive();
            $this->repo->createBatch($prices);
        });

        return [
            'processed'      => count($prices),
            'skipped'        => count($skipped),
            'skipped_detail' => $skipped,
        ];
    }
}
