<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificados de calibración de los equipos de monitoreo (sensores).
 *
 * Cada equipo tiene a lo sumo un certificado vigente (`is_current = true`).
 * Al cargar uno nuevo, el anterior se conserva como historial
 * (`is_current = false`, `replaced_at` con la fecha del reemplazo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_calibration_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors');
            $table->string('certificate_number', 100)->nullable();
            $table->string('laboratory', 150)->nullable();
            $table->date('calibrated_at')->nullable();
            $table->date('expires_at');
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('replaced_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sensor_id', 'is_current'], 'idx_calibration_sensor_current');
            $table->index('expires_at', 'idx_calibration_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_calibration_certificates');
    }
};
