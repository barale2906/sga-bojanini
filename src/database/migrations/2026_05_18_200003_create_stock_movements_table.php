<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('warehouse_to_id')->nullable()->constrained('warehouses');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('batch_id')->nullable()->constrained('batches');
            $table->foreignId('location_from_id')->nullable()->constrained('locations');
            $table->foreignId('location_to_id')->nullable()->constrained('locations');
            $table->enum('movement_type', [
                'entry', 'exit', 'transfer', 'adjustment',
                'return', 'expiration_write_off', 'loss',
            ]);
            $table->integer('quantity');
            $table->text('reason')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Centro de costo: requerido en movimientos de salida
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
            // Servicio médico: requerido cuando el centro de costo es externo (paciente)
            $table->foreignId('service_id')->nullable()->constrained('medical_services');
            // Datos del paciente: vienen de la API externa, solo para centros externos
            $table->string('patient_document', 50)->nullable();
            $table->string('patient_external_id', 100)->nullable();

            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at'], 'idx_mov_product_created');
            $table->index(['movement_type', 'created_at'], 'idx_mov_type_created');
            $table->index(['cost_center_id', 'created_at'], 'idx_mov_costcenter_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
