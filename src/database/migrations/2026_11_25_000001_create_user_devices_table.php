<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispositivos móviles (token FCM) donde se entregan las notificaciones push.
 *
 * El token es único: si un teléfono compartido cambia de usuario, la fila se
 * reasigna al nuevo y el anterior deja de recibir push en ese teléfono.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('platform', 20);
            $table->string('device_name', 100)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_user_devices_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
