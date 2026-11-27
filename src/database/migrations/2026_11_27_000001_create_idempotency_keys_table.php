<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de idempotencia: una misma solicitud de creación enviada dos veces
 * (p. ej. por un reintento tras perder la conexión) se ejecuta una sola vez y
 * la repetición recibe la respuesta original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('path', 150);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key', 'path'], 'uq_idempotency_user_key_path');
            $table->index('created_at', 'idx_idempotency_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
