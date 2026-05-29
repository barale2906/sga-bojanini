<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Código único de clasificación (ej: MED, DM, OTR)');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('has_sanitary_registration')->default(false)
                ->comment('Aplica registro sanitario');
            $table->boolean('has_concentration')->default(false)
                ->comment('Aplica campo concentración (medicamentos)');
            $table->boolean('has_risk_level')->default(false)
                ->comment('Aplica nivel de riesgo (dispositivos médicos)');
            $table->boolean('has_pharma_fields')->default(false)
                ->comment('Aplica forma farmacéutica y presentación comercial');
            $table->boolean('has_device_fields')->default(false)
                ->comment('Aplica serie/referencia y vida útil');
            $table->boolean('has_lab_brand')->default(false)
                ->comment('Requiere laboratorio/marca');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_classifications');
    }
};
