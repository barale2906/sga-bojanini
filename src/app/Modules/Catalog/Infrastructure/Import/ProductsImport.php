<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Import;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductsImport implements ToArray, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets, SkipsUnknownSheets
{
    private array $rows = [];

    private CategoriesImport $categoriesImport;

    private UnitsOfMeasureImport $unitsOfMeasureImport;

    private ProductClassificationsImport $classificationsImport;

    public function __construct()
    {
        $this->categoriesImport = new CategoriesImport();
        $this->unitsOfMeasureImport = new UnitsOfMeasureImport();
        $this->classificationsImport = new ProductClassificationsImport();
    }

    public function array(array $rows): void
    {
        $this->rows = array_map(
            fn (array $row) => array_map(
                fn ($value) => is_null($value) || is_bool($value)
                    ? $value
                    : trim((string) $value),
                $row,
            ),
            $rows,
        );
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function getCategoryRows(): array
    {
        return $this->categoriesImport->getRows();
    }

    public function getUnitOfMeasureRows(): array
    {
        return $this->unitsOfMeasureImport->getRows();
    }

    public function getClassificationRows(): array
    {
        return $this->classificationsImport->getRows();
    }

    /**
     * Procesa la hoja "Productos" con esta misma clase, y delega las hojas
     * "Categorías", "Unidades de medida" y "Clasificaciones" a importadores
     * dedicados (la hoja "Instrucciones" se ignora). El controlador procesa
     * esas hojas de catálogo ANTES que "Productos", para que los códigos
     * nuevos creados allí estén disponibles al validar
     * category_code/unit_abbreviation/classification_code.
     */
    public function sheets(): array
    {
        return [
            'Productos'          => $this,
            'Categorías'         => $this->categoriesImport,
            'Unidades de medida' => $this->unitsOfMeasureImport,
            'Clasificaciones'    => $this->classificationsImport,
        ];
    }

    /**
     * Si el archivo subido no incluye alguna de las hojas de catálogo
     * (p. ej. "Categorías", "Unidades de medida" o "Clasificaciones"),
     * simplemente se ignora en lugar de fallar toda la importación.
     */
    public function onUnknownSheet($sheetName): void
    {
    }
}
