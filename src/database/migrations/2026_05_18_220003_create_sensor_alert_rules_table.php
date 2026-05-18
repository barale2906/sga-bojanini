<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensor_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained('sensors')->cascadeOnDelete();
            $table->enum('condition_type', ['above', 'below', 'trend_up', 'trend_down', 'out_of_range']);
            $table->decimal('threshold', 8, 2)->nullable();
            $table->unsignedInteger('consecutive_readings')->default(1);
            $table->json('notification_channels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_alert_rules');
    }
};
