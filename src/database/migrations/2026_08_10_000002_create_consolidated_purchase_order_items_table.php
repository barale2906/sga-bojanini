<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidated_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consolidated_order_id')
                ->constrained('consolidated_purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->unsignedBigInteger('product_presentation_id');
            $table->foreign('product_presentation_id', 'cpo_items_presentation_fk')
                ->references('id')->on('product_presentations');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_price', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidated_purchase_order_items');
    }
};
