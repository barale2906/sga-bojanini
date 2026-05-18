<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('lot_number', 100);
            $table->date('expiration_date');
            $table->date('manufacturing_date')->nullable();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_available');
            $table->enum('status', ['active', 'expired', 'depleted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['product_id', 'lot_number'], 'uq_batches_product_lot');
            $table->index(['expiration_date', 'status'], 'idx_batches_expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
