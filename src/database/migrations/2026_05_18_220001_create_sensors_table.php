<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones');
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->enum('type', ['temperature', 'humidity']);
            $table->string('unit', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Asignación de sensores (equipos de monitoreo) a usuarios: un usuario
        // puede tener acceso a varios sensores y un sensor puede ser gestionado
        // por varios usuarios. El rol super_administrador bypasea esta
        // restricción a nivel de aplicación (ver SensorAccessService), pero
        // igualmente recibe una fila aquí por cada sensor nuevo para mantener
        // la asignación explícita y consistente.
        Schema::create('user_sensor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'sensor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sensor');
        Schema::dropIfExists('sensors');
    }
};
