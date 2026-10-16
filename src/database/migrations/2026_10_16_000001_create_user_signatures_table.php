<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma registrada por usuario ("Mi firma").
 *
 * Cada usuario registra una sola vez su documento de identidad y su firma
 * manuscrita; al confirmar un movimiento de inventario la firma se aplica
 * automáticamente (quien entrega) o validando un PIN (quien recibe).
 *
 * Las filas NO se editan: cada cambio crea una nueva versión y desactiva la
 * anterior, de modo que los comprobantes ya firmados siguen apuntando a la
 * versión exacta con la que se firmaron.
 *
 * `pin_lookup` guarda un HMAC-SHA256 del PIN (nunca el PIN en claro). Es único
 * porque el PIN, por sí solo, identifica al firmante. Solo la versión activa
 * lo conserva; las versiones históricas quedan en NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 10);
            $table->string('document_number', 50);
            $table->longText('signature_data');
            $table->string('pin_lookup', 64)->nullable()->unique('uq_user_signatures_pin_lookup');
            $table->timestamp('pin_generated_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'idx_user_signatures_user_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_signatures');
    }
};
