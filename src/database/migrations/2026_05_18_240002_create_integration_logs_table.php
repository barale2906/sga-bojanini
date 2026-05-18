<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained('external_integrations');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('endpoint', 500);
            $table->string('method', 10);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
