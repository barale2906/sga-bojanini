<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->foreignId('product_presentation_id')
                ->nullable()
                ->constrained('product_presentations')
                ->nullOnDelete();
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['product_variant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_supplier');
    }
};
