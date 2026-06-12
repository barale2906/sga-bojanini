<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use Illuminate\Support\Facades\Validator;

class CategoryImportService
{
    /**
     * Crea categorías nuevas a partir de la hoja "Categorías" de la plantilla.
     * Las filas cuyo `code` ya existe en la base de datos se omiten (no se
     * consideran error, ya que esa hoja también se entrega como referencia).
     *
     * @return array{total: int, created: int, skipped: int, failed: int, errors: array}
     */
    public function import(array $rows): array
    {
        $results = [
            'total'   => count($rows),
            'created' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if (! empty($row['code']) && CategoryModel::where('code', $row['code'])->exists()) {
                $results['skipped']++;
                continue;
            }

            $validator = Validator::make($row, [
                'code'        => 'required|string|max:50',
                'name'        => 'required|string|max:255',
                'parent_code' => 'nullable|string|exists:categories,code',
                'description' => 'nullable|string',
            ], [
                'code.required'      => 'El código de la categoría es obligatorio.',
                'name.required'      => 'El nombre de la categoría es obligatorio.',
                'parent_code.exists' => 'No existe ninguna categoría con el código indicado en "parent_code".',
            ]);

            if ($validator->fails()) {
                $results['failed']++;
                $results['errors'][] = [
                    'row'    => $rowNumber,
                    'errors' => $validator->errors()->toArray(),
                ];
                continue;
            }

            $parent = ! empty($row['parent_code'])
                ? CategoryModel::where('code', $row['parent_code'])->first()
                : null;

            CategoryModel::create([
                'parent_id'   => $parent?->id,
                'code'        => $row['code'],
                'name'        => $row['name'],
                'description' => $row['description'] ?? null,
                'is_active'   => true,
            ]);

            $results['created']++;
        }

        return $results;
    }
}
