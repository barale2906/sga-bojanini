<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_presentations')->nullOnDelete();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->foreignId('units_of_measure_id')->constrained('units_of_measure');
            $table->unsignedInteger('quantity_per_parent')->nullable();
            $table->unsignedInteger('factor_to_base');
            $table->unsignedTinyInteger('level');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_presentations');
    }
};
