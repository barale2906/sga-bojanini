<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Support\Facades\Validator;

class SupplierImportService
{
    /**
     * @return array{total: int, success: int, failed: int, errors: array}
     */
    public function import(array $rows): array
    {
        $results = [
            'total'   => count($rows),
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $validator = Validator::make($row, [
                    'name'   => 'required|string|max:255',
                    'tax_id' => 'nullable|string|max:50|unique:suppliers,tax_id',
                    'email'  => 'nullable|email|max:255',
                ], [
                    'name.required' => 'El nombre es obligatorio.',
                    'tax_id.unique'  => 'Ya existe un proveedor con este NIT/identificación.',
                    'email.email'    => 'El correo electrónico no tiene un formato válido.',
                ]);

                if ($validator->fails()) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row'    => $rowNumber,
                        'errors' => $validator->errors()->toArray(),
                    ];
                    continue;
                }

                SupplierModel::create([
                    'name'          => $row['name'],
                    'tax_id'        => $row['tax_id'] ?? null,
                    'contact_name'  => $row['contact_name'] ?? null,
                    'phone'         => $row['phone'] ?? null,
                    'email'         => $row['email'] ?? null,
                    'address'       => $row['address'] ?? null,
                    'notes'         => $row['notes'] ?? null,
                ]);

                $results['success']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row'    => $rowNumber,
                    'errors' => ['exception' => [$e->getMessage()]],
                ];
            }
        }

        return $results;
    }
}
