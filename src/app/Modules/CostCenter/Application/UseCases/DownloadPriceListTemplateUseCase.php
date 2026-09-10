<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DownloadPriceListTemplateUseCase
{
    public function execute(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Lista de Precios');

        $sheet->setCellValue('A1', 'procedure_code');
        $sheet->setCellValue('B1', 'procedure_name');
        $sheet->setCellValue('C1', 'service_name');
        $sheet->setCellValue('D1', 'unit_price');

        $today = now()->format('Y-m-d');

        $procedures = MedicalServiceModel::where('type', 'procedure')
            ->where('is_active', true)
            ->with('parent')
            ->orderBy('code')
            ->get();

        $row = 2;
        foreach ($procedures as $proc) {
            $currentPrice = ProcedurePriceModel::where('medical_service_id', $proc->id)
                ->where('is_active', true)
                ->where('effective_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
                })
                ->orderByDesc('effective_from')
                ->value('unit_price');

            $sheet->setCellValue("A{$row}", $proc->code);
            $sheet->setCellValue("B{$row}", $proc->name);
            $sheet->setCellValue("C{$row}", $proc->parent?->name ?? '');
            $sheet->setCellValue("D{$row}", $currentPrice ?? 0);
            $row++;
        }

        return $spreadsheet;
    }
}
