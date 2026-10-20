<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Pdf;

use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Collection;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Documentos PDF del módulo de activos: hoja de vida, acta de traslado,
 * reporte de mantenimiento y etiquetas con código de barras.
 */
class AssetPdfRenderer
{
    public function lifeSheet(AssetModel $asset): Response
    {
        $asset->load([
            'category', 'currentPlace.site', 'responsible', 'supplier', 'technicalProfile',
            'maintenancePlans' => fn ($q) => $q->where('is_active', true)->with(['openOrder', 'technician', 'supplier']),
            'maintenances'     => fn ($q) => $q->where('status', MaintenanceStatus::Completed->value)
                ->with(['technician', 'supplier', 'registeredBy'])->orderByDesc('performed_at'),
            'locationHistory.place.site', 'locationHistory.transfer',
            'documents', 'comments.user',
        ]);

        return $this->render('reports.assets.life_sheet', [
            'asset'   => $asset,
            'barcode' => $this->barcode($asset->code),
        ], "hoja-de-vida-{$asset->code}.pdf");
    }

    public function transferAct(AssetTransferModel $transfer): Response
    {
        $transfer->loadMissing(['asset.category', 'fromPlace.site', 'toPlace.site', 'supplier', 'fromResponsible', 'toResponsible', 'requester', 'signatures']);

        return $this->render('reports.assets.transfer_act', [
            'transfer'   => $transfer,
            'signatures' => $transfer->signatures->keyBy('role'),
        ], "acta-traslado-{$transfer->id}.pdf");
    }

    public function maintenanceReport(AssetMaintenanceModel $maintenance): Response
    {
        $maintenance->loadMissing(['asset.category', 'asset.currentPlace.site', 'technician', 'supplier', 'registeredBy', 'documents', 'signatures']);

        return $this->render('reports.assets.maintenance_report', [
            'maintenance' => $maintenance,
            'signatures'  => $maintenance->signatures->keyBy('role'),
        ], "mantenimiento-{$maintenance->id}.pdf");
    }

    /** @param Collection<int, AssetModel> $assets */
    public function labels(Collection $assets): Response
    {
        $assets->loadMissing('currentPlace.site');

        return $this->render('reports.assets.labels', [
            'labels' => $assets->map(fn (AssetModel $asset) => [
                'asset'   => $asset,
                'barcode' => $this->barcode($asset->code),
            ]),
        ], 'etiquetas-activos.pdf');
    }

    /** Code128 como data URI PNG (dompdf no renderiza SVG de forma confiable). */
    private function barcode(string $value): string
    {
        $png = (new BarcodeGeneratorPNG())->getBarcode($value, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 50);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    private function render(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('letter', 'portrait');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
