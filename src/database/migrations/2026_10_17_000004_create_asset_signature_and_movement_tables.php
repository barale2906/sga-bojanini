<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firmas de documentos de activos (traslados y mantenimientos) y bitácora de eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('signable_type', 30)->comment('transfer | maintenance');
            $table->unsignedBigInteger('signable_id');
            $table->enum('role', ['delivered_by', 'received_by']);
            $table->enum('method', ['pad', 'stored', 'pin']);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_signature_id')->nullable()->constrained('user_signatures')->nullOnDelete();
            $table->string('signer_name', 150);
            $table->string('signer_document', 50);
            $table->longText('signature_data');
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['signable_type', 'signable_id', 'role'], 'uq_asset_signature_role');
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->enum('type', ['registration', 'import', 'update', 'transfer', 'status_change', 'maintenance', 'retirement']);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('transfer_id')->nullable()->constrained('asset_transfers')->nullOnDelete();
            $table->foreignId('maintenance_id')->nullable()->constrained('asset_maintenances')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'created_at'], 'idx_asset_movements_asset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('asset_signatures');
    }
};
