<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `report_exports`, que rastrea cada reporte solicitado por
 * un usuario desde el módulo de Reportes: su estado de generación
 * (queued/processing/ready/failed), dónde quedó el archivo temporal
 * mientras puede descargarse, y cuándo expira.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('format', 10);
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('file_path')->nullable();
            $table->string('file_disk', 20)->default('local');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_report_exports_user_status');
            $table->index('expires_at', 'idx_report_exports_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
