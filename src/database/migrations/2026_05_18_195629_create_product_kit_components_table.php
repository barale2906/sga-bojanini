<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_kit_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_generic_id')->constrained('product_generics')->cascadeOnDelete();
            $table->foreignId('component_generic_id')->constrained('product_generics')->restrictOnDelete();
            $table->unsignedInteger('quantity_per_kit');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kit_generic_id', 'component_generic_id'], 'kit_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_kit_components');
    }
};
