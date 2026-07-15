<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReorderSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'generic_product_id' => $this->resource['generic_product_id'],
            'product_name'       => $this->resource['product_name'],
            'product_barcode'    => $this->resource['product_barcode'],
            'current_stock'      => $this->resource['current_stock'],
            'reorder_point'      => $this->resource['reorder_point'],
            'daily_consumption'  => $this->resource['daily_consumption'],
            'lead_time_days'     => $this->resource['lead_time_days'],
            'safety_stock'       => $this->resource['safety_stock'],
            'suggested_quantity' => $this->resource['suggested_quantity'],
            'preferred_supplier' => $this->resource['preferred_supplier'],
        ];
    }
}
