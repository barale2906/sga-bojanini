<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_generics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('classification_id')
                ->nullable()
                ->constrained('product_classifications')
                ->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units_of_measure');
            $table->enum('product_type', ['simple', 'kit'])->default('simple');
            $table->string('name', 255);
            $table->char('barcode', 6)->unique()->comment('Código interno secuencial 000001–999999 (Code128)');
            $table->text('description')->nullable();
            $table->string('concentration', 100)->nullable()->comment('Concentración del medicamento (ej: 500mg/5ml)');
            $table->string('pharmaceutical_form', 150)->nullable()->comment('Forma farmacéutica (ej: Tableta, Jarabe, Ampolla)');
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
        Schema::dropIfExists('product_generics');
    }
};
