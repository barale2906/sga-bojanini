<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 30)->unique();
            $table->enum('document_type', [
                'entry', 'exit', 'transfer', 'adjustment',
                'return', 'expiration_write_off', 'loss',
            ]);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('warehouse_to_id')->nullable()->constrained('warehouses');

            // Datos comunes de la operación (salidas/traslados)
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
            $table->foreignId('service_id')->nullable()->constrained('medical_services');
            $table->string('patient_document', 50)->nullable();
            $table->string('patient_external_id', 100)->nullable();

            // Datos exclusivos de entradas
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('entry_temperature', 5, 2)->nullable();

            $table->text('reason')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('status', ['pending_signature', 'confirmed'])->default('confirmed');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_type', 'created_at'], 'idx_doc_type_created');
            $table->index(['warehouse_id', 'created_at'], 'idx_doc_warehouse_created');
            $table->index(['cost_center_id', 'created_at'], 'idx_doc_costcenter_created');
            $table->index(['status', 'created_at'], 'idx_doc_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_documents');
    }
};
