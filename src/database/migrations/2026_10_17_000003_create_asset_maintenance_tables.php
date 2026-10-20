<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planes de mantenimiento, órdenes (cronograma + ejecución) y documentos/soportes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->enum('maintenance_type', ['preventive', 'calibration', 'metrological_verification', 'inspection']);
            $table->unsignedSmallInteger('frequency_value');
            $table->enum('frequency_unit', ['days', 'months']);
            $table->date('start_date')->comment('Fecha de la primera ocurrencia');
            $table->enum('performed_by_type', ['internal', 'external']);
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'is_active'], 'idx_asset_plans_asset_active');
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('asset_maintenance_plans')->nullOnDelete();
            $table->enum('maintenance_type', ['preventive', 'calibration', 'metrological_verification', 'inspection', 'corrective']);
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->date('scheduled_date');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('performed_at')->nullable()->comment('Fecha real de ejecución');
            $table->enum('performed_by_type', ['internal', 'external'])->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('external_technician_name', 150)->nullable();
            $table->text('reported_failure')->nullable();
            $table->text('activities_performed')->nullable();
            $table->text('findings')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('parts_replaced')->nullable()->comment('[{name, quantity, cost}]');
            $table->decimal('cost', 15, 2)->nullable()->comment('Informativo');
            $table->enum('result', ['operational', 'operational_with_restrictions', 'out_of_service'])->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete()->comment('Quien registró la ejecución');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_date'], 'idx_asset_maint_status_date');
            $table->index(['asset_id', 'status'], 'idx_asset_maint_asset_status');
        });

        Schema::create('asset_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('maintenance_id')->nullable()->constrained('asset_maintenances')->nullOnDelete()
                ->comment('Con valor: soporte de un mantenimiento');
            $table->enum('document_type', [
                'manual', 'invoice', 'warranty', 'invima', 'calibration_certificate',
                'service_report', 'photo', 'protocol', 'other',
            ]);
            $table->string('title', 150);
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'maintenance_id'], 'idx_asset_documents_asset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');

        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_maintenance_plans');
    }
};
