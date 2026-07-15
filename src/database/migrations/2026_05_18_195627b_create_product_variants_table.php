<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generic_product_id')->constrained('product_generics')->cascadeOnDelete();
            $table->string('lab_brand', 255)->nullable()->comment('Laboratorio fabricante o marca comercial');
            $table->string('brand_sku', 100)->nullable()->comment('SKU del proveedor/fabricante');
            $table->string('commercial_presentation', 150)->nullable()->comment('Presentación comercial (ej: Frasco x120ml, Caja x10 Tab)');
            $table->string('serie_reference', 150)->nullable()->comment('Serie o referencia del dispositivo médico');
            $table->string('useful_life', 100)->nullable()->comment('Vida útil del dispositivo médico (ej: 5 años, Permanente)');
            $table->string('risk_level', 100)->nullable()->comment('Nivel de riesgo del dispositivo médico (ej: Clase IIA)');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
