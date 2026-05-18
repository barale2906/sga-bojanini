<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->enum('type', ['scheduling', 'clinical_records']);
            $table->string('base_url', 500);
            $table->enum('auth_type', ['bearer', 'api_key', 'oauth2', 'basic']);
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_integrations');
    }
};
