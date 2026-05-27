<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('code', 50);
            $table->decimal('volume_cm3', 12, 2)->nullable()->comment('Volumen total disponible en la ubicación (cm³)');
            $table->decimal('max_weight_kg', 10, 2)->nullable()->comment('Peso máximo soportado por la ubicación (kg)');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['zone_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
