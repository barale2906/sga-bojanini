<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_procedure_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_service_id')
                  ->constrained('medical_services')
                  ->restrictOnDelete();
            $table->string('patient_external_id', 100);
            $table->string('patient_document', 50);
            $table->string('patient_first_name', 100);
            $table->string('patient_last_name', 100);
            $table->decimal('quantity', 10, 4);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 14, 2);
            $table->date('service_date');
            $table->text('notes')->nullable();
            $table->string('seller', 150)->nullable();
            $table->string('referrer', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_external_id', 'service_date']);
            $table->index(['medical_service_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_procedure_records');
    }
};
