<?php

declare(strict_types=1);

namespace App\Rules;

use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ExpenseSupplierRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $supplier = SupplierModel::find($value);

        if ($supplier === null) {
            return; // exists: rule ya lo valida antes
        }

        if (! in_array($supplier->supplier_type, ['expense', 'both'], true)) {
            $fail('El proveedor seleccionado no está habilitado para órdenes de gasto. Tipo actual: ' . $supplier->supplier_type . '.');
        }
    }
}
