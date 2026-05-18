<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->unsignedInteger('quantity_kits');
            $table->foreignId('user_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kit_product_id', 'created_at'], 'idx_kit_tx_product_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_transactions');
    }
};
