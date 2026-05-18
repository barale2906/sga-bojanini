<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Monitoring\Application\UseCases\GenerateConditionPdfUseCase;
use App\Modules\Monitoring\Infrastructure\Http\Requests\GenerateConditionReportRequest;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class ConditionReportController extends Controller
{
    use ApiResponse;

    public function generate(GenerateConditionReportRequest $request, GenerateConditionPdfUseCase $useCase): JsonResponse
    {
        $path = $useCase->execute(
            (int) $request->validated('sensor_id'),
            $request->validated('date_from'),
            $request->validated('date_to'),
        );

        return $this->success([
            'path'     => $path,
            'filename' => basename($path),
        ], 'Reporte PDF generado correctamente', 201);
    }

    public function index(): JsonResponse
    {
        $directory = storage_path('app/exports');

        if (!is_dir($directory)) {
            File::ensureDirectoryExists($directory);
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file) => $file->getExtension() === 'pdf')
            ->map(fn ($file) => [
                'filename'  => $file->getFilename(),
                'path'      => $file->getPathname(),
                'size'      => $file->getSize(),
                'modified'  => date('Y-m-d H:i:s', $file->getMTime()),
            ])
            ->sortByDesc('modified')
            ->values()
            ->all();

        return $this->success($files, 'Listado de reportes generados');
    }
}
