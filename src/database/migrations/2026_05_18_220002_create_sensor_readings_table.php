<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors');
            $table->decimal('value', 8, 2);
            $table->enum('reading_source', ['manual', 'iot']);
            $table->timestamp('recorded_at');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sensor_id', 'recorded_at'], 'idx_readings_sensor_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_readings');
    }
};
