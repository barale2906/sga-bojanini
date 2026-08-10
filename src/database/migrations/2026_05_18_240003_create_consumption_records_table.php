<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_records', function (Blueprint $table) {
            $table->id();
            $table->string('appointment_id', 100)->nullable();
            $table->string('patient_identifier', 100)->nullable();
            $table->string('service_type', 255)->nullable();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('batch_id')->nullable()->constrained('batches');
            $table->decimal('quantity', 12, 3);
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('consumed_at');
            $table->timestamp('synced_to_hc_at')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_records');
    }
};
