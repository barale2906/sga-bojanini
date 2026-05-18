<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('product_id')->constrained('products');
            $table->integer('total_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('available_quantity')->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['warehouse_id', 'product_id'], 'uq_stock_sum_wh_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_summaries');
    }
};
