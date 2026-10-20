<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\Import\AssetImportService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ImportLogModel;

/** Importa activos desde el archivo de plantilla y deja registro en import_logs. */
class ImportAssetsUseCase
{
    public const ENTITY_TYPE = 'assets';

    public function __construct(
        private readonly AssetImportService $service,
    ) {}

    /**
     * @param array<string, array<int, array<string, mixed>>> $sheets filas por hoja
     */
    public function execute(array $sheets, string $fileName, int $userId): array
    {
        $results = $this->service->import($sheets, $userId);

        ImportLogModel::create([
            'file_name'    => $fileName,
            'entity_type'  => self::ENTITY_TYPE,
            'total_rows'   => $results['total'],
            'success_rows' => $results['created'],
            'error_rows'   => $results['failed'],
            'errors'       => $results['errors'],
            'user_id'      => $userId,
        ]);

        return $results;
    }
}
