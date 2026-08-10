<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreLossRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'location_id'  => ['required', 'integer', 'exists:locations,id'],
            'batch_id'     => ['required', 'integer', 'exists:batches,id'],
            'quantity'     => ['required', 'numeric', 'min:0.001'],
            'reason'       => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $batchId = $this->input('batch_id');
            $productId = $this->input('product_variant_id');

            if (! $batchId || ! $productId) {
                return;
            }

            $belongsToProduct = BatchModel::where('id', $batchId)
                ->where('product_variant_id', $productId)
                ->exists();

            if (! $belongsToProduct) {
                $v->errors()->add('batch_id', 'El lote seleccionado no corresponde al producto indicado.');
            }
        });
    }
}
