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
            $table->foreignId('kit_generic_id')->constrained('product_generics');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->unsignedInteger('quantity_kits');
            $table->foreignId('user_id')->constrained('users');
            $table->text('reason')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kit_generic_id', 'created_at'], 'idx_kit_tx_generic_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_transactions');
    }
};
