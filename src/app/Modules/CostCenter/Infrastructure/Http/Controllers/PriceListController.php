<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\UseCases\DownloadPriceListTemplateUseCase;
use App\Modules\CostCenter\Application\UseCases\GetCurrentPriceForProcedureUseCase;
use App\Modules\CostCenter\Application\UseCases\UploadPriceListUseCase;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @group Listas de Precios
 *
 * Carga masiva y consulta de precios de procedimientos.
 */
class PriceListController extends Controller
{
    use ApiResponse;

    /**
     * Descargar plantilla de precios (Excel).
     */
    public function template(DownloadPriceListTemplateUseCase $useCase): StreamedResponse
    {
        $spreadsheet = $useCase->execute();
        $filename    = 'lista_precios_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Importar lista de precios desde Excel.
     *
     * @bodyParam file file required Archivo Excel (.xlsx / .xls).
     */
    public function import(Request $request, UploadPriceListUseCase $useCase): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        $result = $useCase->execute($request->file('file')->getPathname(), (int) auth()->id());

        return $this->success($result, 'Lista de precios cargada exitosamente');
    }

    /**
     * Precio vigente de un procedimiento.
     *
     * @urlParam id integer required ID del servicio médico (procedimiento). Example: 5
     */
    public function currentPrice(int $id, GetCurrentPriceForProcedureUseCase $useCase): JsonResponse
    {
        $price = $useCase->execute($id);

        if ($price === null) {
            return $this->success(['price' => null], 'Sin precio vigente para este procedimiento');
        }

        return $this->success([
            'medical_service_id' => $price->getMedicalServiceId(),
            'unit_price'         => $price->getUnitPrice(),
            'effective_from'     => $price->getEffectiveFrom()->format('Y-m-d'),
            'effective_to'       => $price->getEffectiveTo()?->format('Y-m-d'),
        ], 'Precio vigente');
    }
}
