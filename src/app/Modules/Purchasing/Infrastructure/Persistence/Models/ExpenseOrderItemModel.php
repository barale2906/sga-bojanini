<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseOrderItemModel extends Model
{
    protected $table = 'expense_order_items';

    protected $fillable = [
        'purchase_order_id',
        'description',
        'unit',
        'quantity_requested',
        'quantity_received',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'total_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:3',
            'quantity_received'  => 'decimal:3',
            'unit_price'         => 'decimal:2',
            'tax_rate'           => 'decimal:2',
            'tax_amount'         => 'decimal:2',
            'total_price'        => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderModel::class, 'purchase_order_id');
    }
}
