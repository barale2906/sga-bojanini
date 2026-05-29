<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('classification_id')
                ->nullable()
                ->constrained('product_classifications')
                ->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units_of_measure');
            $table->enum('product_type', ['simple', 'kit'])->default('simple');
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->string('sku', 100)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('concentration', 100)->nullable()->comment('Concentración del medicamento (ej: 500mg/5ml)');
            $table->string('risk_level', 100)->nullable()->comment('Nivel de riesgo del dispositivo médico (ej: Clase IIA)');
            $table->string('lab_brand', 255)->nullable()->comment('Laboratorio fabricante o marca comercial');
            $table->string('pharmaceutical_form', 150)->nullable()->comment('Forma farmacéutica (ej: Tableta, Jarabe, Ampolla)');
            $table->string('commercial_presentation', 150)->nullable()->comment('Presentación comercial (ej: Frasco x120ml, Caja x10 Tab)');
            $table->string('serie_reference', 150)->nullable()->comment('Serie o referencia del dispositivo médico');
            $table->string('useful_life', 100)->nullable()->comment('Vida útil del dispositivo médico (ej: 5 años, Permanente)');
            $table->decimal('volume_cm3', 12, 2)->nullable()->comment('Volumen de una unidad del producto (cm³)');
            $table->decimal('weight_kg', 10, 3)->nullable()->comment('Peso de una unidad del producto (kg)');
            $table->boolean('requires_cold_chain')->default(false);
            $table->unsignedInteger('reorder_point')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->unsignedInteger('max_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
