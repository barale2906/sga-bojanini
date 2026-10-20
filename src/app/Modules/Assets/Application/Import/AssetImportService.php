<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Import;

use App\Modules\Assets\Application\DTOs\AssetCategoryData;
use App\Modules\Assets\Application\DTOs\AssetData;
use App\Modules\Assets\Application\DTOs\AssetPlaceData;
use App\Modules\Assets\Application\DTOs\AssetSiteData;
use App\Modules\Assets\Application\DTOs\MaintenancePlanData;
use App\Modules\Assets\Application\UseCases\CreateAssetUseCase;
use App\Modules\Assets\Application\UseCases\SaveAssetCategoryUseCase;
use App\Modules\Assets\Application\UseCases\SaveAssetPlaceUseCase;
use App\Modules\Assets\Application\UseCases\SaveAssetSiteUseCase;
use App\Modules\Assets\Application\UseCases\SaveMaintenancePlanUseCase;
use App\Modules\Assets\Application\UseCases\UpsertTechnicalProfileUseCase;
use App\Modules\Assets\Domain\Enums\AcquisitionMode;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\BiomedicalClass;
use App\Modules\Assets\Domain\Enums\FrequencyUnit;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Assets\Domain\Enums\PowerSource;
use App\Modules\Assets\Domain\Enums\RiskClass;
use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetPlaceRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetSiteRepositoryInterface;
use App\Modules\Assets\Domain\Services\MaintenanceScheduleCalculator;
use App\Modules\Assets\Infrastructure\Import\AssetsImport;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Procesa el archivo de importación de activos.
 *
 * Orden: 1) catálogos (Categorías, Sedes y sitios) para que los códigos
 * nuevos estén disponibles; 2) activos (cada fila en su propia transacción,
 * con plan de mantenimiento opcional); 3) hojas de vida, relacionadas por serial.
 * Una fila con error no detiene las demás.
 */
class AssetImportService
{
    /** Catálogos ya resueltos durante la importación. */
    private array $siteIds = [];

    private array $placeIds = [];

    /** @var array<string, int> serial => asset_id de los activos creados en este archivo */
    private array $importedSerials = [];

    public function __construct(
        private readonly AssetCategoryRepositoryInterface $categories,
        private readonly AssetSiteRepositoryInterface $sites,
        private readonly AssetPlaceRepositoryInterface $places,
        private readonly SaveAssetCategoryUseCase $saveCategory,
        private readonly SaveAssetSiteUseCase $saveSite,
        private readonly SaveAssetPlaceUseCase $savePlace,
        private readonly CreateAssetUseCase $createAsset,
        private readonly SaveMaintenancePlanUseCase $savePlan,
        private readonly UpsertTechnicalProfileUseCase $upsertProfile,
        private readonly MaintenanceScheduleCalculator $calculator,
    ) {}

    /**
     * @param array<string, array<int, array<string, mixed>>> $sheets filas por hoja (AssetsImport::rowsBySheet)
     *
     * @return array{total: int, created: int, failed: int, errors: array, related_catalogs: array, technical_profiles: array}
     */
    public function import(array $sheets, int $userId): array
    {
        $this->siteIds = $this->placeIds = $this->importedSerials = [];

        $relatedCatalogs = [
            'categories' => $this->importCategories($sheets[AssetsImport::SHEET_CATEGORIES] ?? []),
            'places'     => $this->importPlaces($sheets[AssetsImport::SHEET_PLACES] ?? []),
        ];

        $results = ['total' => 0, 'created' => 0, 'failed' => 0, 'errors' => []];

        foreach ($sheets[AssetsImport::SHEET_ASSETS] ?? [] as $index => $row) {
            if (AssetImportLayout::isLabelRow($row, AssetImportLayout::ASSET_COLUMNS)) {
                continue;
            }

            $results['total']++;

            try {
                $this->importAssetRow($row, $userId);
                $results['created']++;
            } catch (RowFailed $e) {
                $results['failed']++;
                $results['errors'][] = ['sheet' => AssetsImport::SHEET_ASSETS, 'row' => $index + 2, 'errors' => $e->errors];
            }
        }

        $profiles = $this->summary();
        $this->importProfiles($sheets[AssetsImport::SHEET_BIOMEDICAL] ?? [], AssetsImport::SHEET_BIOMEDICAL, AssetType::Biomedical, AssetImportLayout::biomedicalColumns(), $profiles);
        $this->importProfiles($sheets[AssetsImport::SHEET_COMPUTING] ?? [], AssetsImport::SHEET_COMPUTING, AssetType::Computing, AssetImportLayout::computingColumns(), $profiles);

        return [
            ...$results,
            'related_catalogs'   => $relatedCatalogs,
            'technical_profiles' => $profiles,
        ];
    }

    // ── Catálogos ─────────────────────────────────────────────────────────

    private function importCategories(array $rows): array
    {
        $summary = $this->summary();

        foreach ($rows as $index => $row) {
            $summary['total']++;
            $code = strtoupper((string) ($row['code'] ?? ''));

            if ($code !== '' && $this->categories->findByCode($code) !== null) {
                $summary['skipped']++;

                continue;
            }

            $this->attempt($summary, AssetsImport::SHEET_CATEGORIES, $index, function () use ($row, $code) {
                $this->validate($row, [
                    'code'       => ['required', 'string', 'max:20'],
                    'name'       => ['required', 'string', 'max:120'],
                    'asset_type' => ['required', Rule::enum(AssetType::class)],
                ]);

                $this->saveCategory->execute(null, new AssetCategoryData(
                    code:        $code,
                    name:        (string) $row['name'],
                    assetType:   (string) $row['asset_type'],
                    description: $row['description'] ?? null,
                ));
            });
        }

        return $summary;
    }

    private function importPlaces(array $rows): array
    {
        $summary = $this->summary();

        foreach ($rows as $index => $row) {
            $summary['total']++;
            $siteName  = (string) ($row['site_name'] ?? '');
            $placeName = (string) ($row['place_name'] ?? '');

            $exists = $siteName !== '' && $placeName !== ''
                && ($site = $this->sites->findByName($siteName)) !== null
                && $this->places->findBySiteAndName($site->getId(), $placeName) !== null;

            if ($exists) {
                $summary['skipped']++;

                continue;
            }

            $this->attempt($summary, AssetsImport::SHEET_PLACES, $index, function () use ($row, $siteName, $placeName) {
                $this->validate($row, [
                    'site_name'  => ['required', 'string', 'max:120'],
                    'place_name' => ['required', 'string', 'max:120'],
                ]);

                $this->resolvePlaceId($siteName, $placeName);
            });
        }

        return $summary;
    }

    // ── Activos ───────────────────────────────────────────────────────────

    private function importAssetRow(array $row, int $userId): void
    {
        $this->validate($row, [
            'name'                  => ['required', 'string', 'max:200'],
            'category_code'         => ['required', 'string'],
            'site_name'             => ['required', 'string', 'max:120'],
            'place_name'            => ['required', 'string', 'max:120'],
            'responsible_email'     => ['nullable', 'email'],
            'brand'                 => ['nullable', 'string', 'max:100'],
            'model'                 => ['nullable', 'string', 'max:100'],
            'serial'                => ['nullable', 'string', 'max:100'],
            'acquisition_value'     => ['nullable', 'numeric', 'min:0'],
            'invoice_number'        => ['nullable', 'string', 'max:50'],
            'status'                => ['nullable', Rule::in(array_map(fn ($s) => $s->value, AssetStatus::manuallySettable()))],
            'maintenance_type'      => ['nullable', Rule::in(array_map(fn ($t) => $t->value, MaintenanceType::plannable()))],
            'frequency_value'       => ['required_with:maintenance_type', 'nullable', 'integer', 'min:1'],
            'frequency_unit'        => ['required_with:maintenance_type', 'nullable', Rule::enum(FrequencyUnit::class)],
            'performed_by_type'     => ['required_with:maintenance_type', 'nullable', Rule::enum(PerformedByType::class)],
        ]);

        $category = $this->categories->findByCode(strtoupper((string) $row['category_code']))
            ?? throw RowFailed::field('category_code', 'La categoría no existe.');

        $responsibleId = null;

        if (! empty($row['responsible_email'])) {
            $responsibleId = UserModel::where('email', $row['responsible_email'])->value('id')
                ?? throw RowFailed::field('responsible_email', 'No existe un usuario con ese correo.');
        }

        $supplierId = null;

        if (! empty($row['supplier_tax_id'])) {
            $supplierId = SupplierModel::where('tax_id', $row['supplier_tax_id'])->value('id')
                ?? throw RowFailed::field('supplier_tax_id', 'No existe un proveedor con ese NIT.');
        }

        $hasPlan = ! empty($row['maintenance_type']);

        if ($hasPlan && ! $category->getAssetType()->requiresMaintenance()) {
            throw RowFailed::field('maintenance_type', 'La categoría es de tipo general: no admite planes de mantenimiento.');
        }

        $acquisitionDate = $this->date($row, 'acquisition_date');
        $warrantyUntil   = $this->date($row, 'warranty_until');
        $lastMaintenance = $this->date($row, 'last_maintenance_date');

        $this->guard(function () use ($row, $category, $responsibleId, $supplierId, $acquisitionDate, $warrantyUntil, $hasPlan, $lastMaintenance, $userId) {
            DB::transaction(function () use ($row, $category, $responsibleId, $supplierId, $acquisitionDate, $warrantyUntil, $hasPlan, $lastMaintenance, $userId) {
                $asset = $this->createAsset->execute(new AssetData(
                    name:             (string) $row['name'],
                    categoryId:       $category->getId(),
                    placeId:          $this->resolvePlaceId((string) $row['site_name'], (string) $row['place_name']),
                    responsibleId:    $responsibleId,
                    status:           $row['status'] ?? null,
                    brand:            $row['brand'] ?? null,
                    model:            $row['model'] ?? null,
                    serial:           $row['serial'] ?? null,
                    acquisitionDate:  $acquisitionDate,
                    acquisitionValue: isset($row['acquisition_value']) ? (string) $row['acquisition_value'] : null,
                    supplierId:       $supplierId,
                    invoiceNumber:    $row['invoice_number'] ?? null,
                    warrantyUntil:    $warrantyUntil,
                    notes:            $row['notes'] ?? null,
                    initialComment:   $row['comment'] ?? null,
                ), $userId, AssetMovementType::Import);

                if ($hasPlan) {
                    $frequencyUnit = FrequencyUnit::from((string) $row['frequency_unit']);

                    $this->savePlan->execute($asset->getId(), null, new MaintenancePlanData(
                        maintenanceType: (string) $row['maintenance_type'],
                        frequencyValue:  (int) $row['frequency_value'],
                        frequencyUnit:   $frequencyUnit->value,
                        startDate:       $this->planStart($lastMaintenance, (int) $row['frequency_value'], $frequencyUnit),
                        performedByType: (string) $row['performed_by_type'],
                    ), $userId);
                }

                if ($asset->getSerial() !== null) {
                    $this->importedSerials[$asset->getSerial()] = $asset->getId();
                }
            });
        });
    }

    /** Fecha de la primera orden: último mantenimiento + frecuencia, o hoy si no se indicó. */
    private function planStart(?string $lastMaintenance, int $value, FrequencyUnit $unit): string
    {
        if ($lastMaintenance === null) {
            return now()->toDateString();
        }

        return $this->calculator->next(new DateTimeImmutable($lastMaintenance), $value, $unit)->format('Y-m-d');
    }

    // ── Hojas de vida ─────────────────────────────────────────────────────

    private function importProfiles(array $rows, string $sheet, AssetType $type, array $columns, array &$summary): void
    {
        foreach ($rows as $index => $row) {
            if (AssetImportLayout::isLabelRow($row, $columns)) {
                continue;
            }

            $summary['total']++;

            $this->attempt($summary, $sheet, $index, function () use ($row, $type, $columns) {
                $this->validate($row, [
                    'serial'               => ['required', 'string'],
                    'manufacture_year'     => ['nullable', 'integer', 'between:1900,2100'],
                    'useful_life_years'    => ['nullable', 'integer', 'between:1,100'],
                    'acquisition_mode'     => ['nullable', Rule::enum(AcquisitionMode::class)],
                    'risk_class'           => ['nullable', Rule::enum(RiskClass::class)],
                    'biomedical_class'     => ['nullable', Rule::enum(BiomedicalClass::class)],
                    'power_source'         => ['nullable', Rule::enum(PowerSource::class)],
                    'ip_address'           => ['nullable', 'ip'],
                    'mac_address'          => ['nullable', 'mac_address'],
                ]);

                $assetId = $this->assetIdBySerial((string) $row['serial'], $type);

                $values = [];

                foreach (array_slice(AssetImportLayout::headers($columns), 1) as $field) {
                    if (array_key_exists($field, $row) && $row[$field] !== null) {
                        $values[$field] = $field === 'requires_calibration'
                            ? filter_var($row[$field], FILTER_VALIDATE_BOOLEAN)
                            : $row[$field];
                    }
                }

                $this->upsertProfile->execute($assetId, $values);
            });
        }
    }

    private function assetIdBySerial(string $serial, AssetType $type): int
    {
        if (isset($this->importedSerials[$serial])) {
            $ids = [$this->importedSerials[$serial]];
        } else {
            $ids = AssetModel::where('serial', $serial)->pluck('id')->all();
        }

        if (count($ids) !== 1) {
            throw RowFailed::field('serial', count($ids) === 0
                ? 'No existe un activo con ese serial.'
                : 'Hay varios activos con ese serial; cargue la hoja de vida desde la ficha del activo.');
        }

        $asset = AssetModel::with('category')->findOrFail($ids[0]);

        if ($asset->category->asset_type !== $type) {
            throw RowFailed::field('serial', "El activo no es de tipo «{$type->label()}».");
        }

        return $asset->id;
    }

    // ── Utilidades ────────────────────────────────────────────────────────

    /** Busca o crea la sede y el sitio (dual-purpose de la hoja "Sedes y sitios"). */
    private function resolvePlaceId(string $siteName, string $placeName): int
    {
        $key = mb_strtolower("{$siteName}|{$placeName}");

        if (isset($this->placeIds[$key])) {
            return $this->placeIds[$key];
        }

        $siteKey = mb_strtolower($siteName);
        $siteId  = $this->siteIds[$siteKey]
            ??= ($this->sites->findByName($siteName) ?? $this->saveSite->execute(null, new AssetSiteData($siteName)))->getId();

        $place = $this->places->findBySiteAndName($siteId, $placeName)
            ?? $this->savePlace->execute(null, new AssetPlaceData($siteId, $placeName));

        return $this->placeIds[$key] = $place->getId();
    }

    private function date(array $row, string $field): ?string
    {
        $value = $row[$field] ?? null;

        if ($value === null) {
            return null;
        }

        try {
            return is_numeric($value)
                ? ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d')
                : (new DateTimeImmutable((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            throw RowFailed::field($field, 'Fecha inválida; use el formato AAAA-MM-DD.');
        }
    }

    private function validate(array $row, array $rules): void
    {
        $validator = Validator::make($row, $rules);

        if ($validator->fails()) {
            throw new RowFailed($validator->errors()->toArray());
        }
    }

    /** Convierte errores de dominio en errores de fila. */
    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (\DomainException $e) {
            throw RowFailed::field('row', $e->getMessage());
        }
    }

    private function attempt(array &$summary, string $sheet, int $index, callable $callback): void
    {
        try {
            $this->guard($callback);
            $summary['created']++;
        } catch (RowFailed $e) {
            $summary['failed']++;
            $summary['errors'][] = ['sheet' => $sheet, 'row' => $index + 2, 'errors' => $e->errors];
        }
    }

    private function summary(): array
    {
        return ['total' => 0, 'created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
    }
}
