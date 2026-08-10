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
            $table->foreignId('movement_document_id')->constrained('movement_documents')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('warehouse_to_id')->nullable()->constrained('warehouses');
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('batch_id')->nullable()->constrained('batches');
            $table->foreignId('location_from_id')->nullable()->constrained('locations');
            $table->foreignId('location_to_id')->nullable()->constrained('locations');
            $table->enum('movement_type', [
                'entry', 'exit', 'transfer', 'adjustment',
                'return', 'expiration_write_off', 'loss',
            ]);
            $table->decimal('quantity', 12, 3);
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

            // Campos exclusivos de entradas
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('entry_temperature', 5, 2)->nullable();

            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['pending_signature', 'confirmed'])->default('confirmed');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_variant_id', 'created_at'], 'idx_mov_variant_created');
            $table->index(['movement_type', 'created_at'], 'idx_mov_type_created');
            $table->index(['cost_center_id', 'created_at'], 'idx_mov_costcenter_created');
            $table->index(['status', 'created_at'], 'idx_mov_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
