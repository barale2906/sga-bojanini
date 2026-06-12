<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Services;

use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class MedicalServiceImportService
{
    /**
     * Importa servicios y procedimientos médicos.
     *
     * Las filas "service" se procesan primero (sin importar su orden en el
     * archivo), de modo que las filas "procedure" puedan referenciar
     * mediante parent_code cualquier servicio definido en el mismo archivo.
     *
     * @return array{total: int, success: int, failed: int, errors: array}
     */
    public function import(array $rows): array
    {
        $results = [
            'total' => count($rows),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $codeMap = MedicalServiceModel::where('type', MedicalServiceType::SERVICE->value)
            ->pluck('id', 'code')
            ->all();

        $serviceRows = [];
        $procedureRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $type = strtolower(trim((string) ($row['type'] ?? '')));

            if ($type === MedicalServiceType::PROCEDURE->value) {
                $procedureRows[$rowNumber] = $row;
            } else {
                $serviceRows[$rowNumber] = $row;
            }
        }

        foreach ($serviceRows as $rowNumber => $row) {
            $this->importService($row, $rowNumber, $codeMap, $results);
        }

        foreach ($procedureRows as $rowNumber => $row) {
            $this->importProcedure($row, $rowNumber, $codeMap, $results);
        }

        return $results;
    }

    private function importService(array $row, int $rowNumber, array &$codeMap, array &$results): void
    {
        try {
            $validator = Validator::make($row, [
                'code' => ['required', 'string', 'max:20', 'unique:medical_services,code'],
                'name' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
                'parent_code' => ['nullable', 'string'],
            ], [
                'code.required' => 'El código es obligatorio.',
                'code.unique' => 'Ya existe un servicio o procedimiento con este código.',
                'name.required' => 'El nombre es obligatorio.',
            ]);

            if ($validator->fails()) {
                $this->fail($results, $rowNumber, $validator->errors()->toArray());

                return;
            }

            $parentId = null;
            $parentCode = trim((string) ($row['parent_code'] ?? ''));

            if ($parentCode !== '') {
                if (! isset($codeMap[$parentCode])) {
                    $this->fail($results, $rowNumber, ['parent_code' => ['No existe ningún servicio con este código. Debe estar definido en una fila anterior o existir previamente.']]);

                    return;
                }
                $parentId = $codeMap[$parentCode];
            }

            $service = MedicalServiceModel::create([
                'parent_id' => $parentId,
                'type' => MedicalServiceType::SERVICE->value,
                'code' => $row['code'],
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active' => $this->toBool($row['is_active'] ?? true),
            ]);

            $codeMap[$row['code']] = $service->id;
            $results['success']++;
        } catch (\Throwable $e) {
            $this->fail($results, $rowNumber, ['exception' => [$e->getMessage()]]);
        }
    }

    private function importProcedure(array $row, int $rowNumber, array &$codeMap, array &$results): void
    {
        try {
            $row['effective_from'] = $this->normalizeDate($row['effective_from'] ?? null);
            $row['effective_to'] = $this->normalizeDate($row['effective_to'] ?? null);

            $validator = Validator::make($row, [
                'code' => ['required', 'string', 'max:20', 'unique:medical_services,code'],
                'name' => ['required', 'string', 'max:100'],
                'description' => ['nullable', 'string'],
                'parent_code' => ['required', 'string'],
                'unit_price' => ['required', 'numeric', 'min:0'],
                'effective_from' => ['required', 'date_format:Y-m-d'],
                'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
                'notes' => ['nullable', 'string', 'max:500'],
            ], [
                'code.required' => 'El código es obligatorio.',
                'code.unique' => 'Ya existe un servicio o procedimiento con este código.',
                'name.required' => 'El nombre es obligatorio.',
                'parent_code.required' => 'El código del servicio al que pertenece este procedimiento es obligatorio.',
                'unit_price.required' => 'El valor del procedimiento es obligatorio.',
                'effective_from.required' => 'La fecha de vigencia es obligatoria.',
                'effective_from.date_format' => 'La fecha de vigencia debe tener el formato AAAA-MM-DD.',
                'effective_to.date_format' => 'La fecha de fin de vigencia debe tener el formato AAAA-MM-DD.',
                'effective_to.after_or_equal' => 'La fecha de fin de vigencia debe ser igual o posterior a la fecha de vigencia.',
            ]);

            if ($validator->fails()) {
                $this->fail($results, $rowNumber, $validator->errors()->toArray());

                return;
            }

            $parentCode = trim((string) $row['parent_code']);

            if (! isset($codeMap[$parentCode])) {
                $this->fail($results, $rowNumber, ['parent_code' => ['No existe ningún servicio con este código. Revise la hoja "Servicios existentes" de la plantilla.']]);

                return;
            }

            DB::transaction(function () use ($row, $codeMap, $parentCode) {
                $procedure = MedicalServiceModel::create([
                    'parent_id' => $codeMap[$parentCode],
                    'type' => MedicalServiceType::PROCEDURE->value,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'is_active' => $this->toBool($row['is_active'] ?? true),
                ]);

                ProcedurePriceModel::create([
                    'medical_service_id' => $procedure->id,
                    'unit_price' => $row['unit_price'],
                    'effective_from' => $row['effective_from'],
                    'effective_to' => $row['effective_to'] ?: null,
                    'is_active' => true,
                    'notes' => $row['notes'] ?? null,
                ]);
            });

            $results['success']++;
        } catch (\Throwable $e) {
            $this->fail($results, $rowNumber, ['exception' => [$e->getMessage()]]);
        }
    }

    private function fail(array &$results, int $rowNumber, array $errors): void
    {
        $results['failed']++;
        $results['errors'][] = [
            'row' => $rowNumber,
            'errors' => $errors,
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return (string) $value;
    }
}
