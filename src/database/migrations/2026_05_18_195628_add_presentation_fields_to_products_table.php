<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_presentation', function (Blueprint $table) {
            $table->foreignId('generic_product_id')->constrained('product_generics')->cascadeOnDelete();
            $table->foreignId('presentation_id')->constrained('product_presentations')->cascadeOnDelete();
            $table->boolean('is_purchase_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['generic_product_id', 'presentation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_presentation');
    }
};
