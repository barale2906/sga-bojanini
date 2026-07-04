<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained('stock_movements')->cascadeOnDelete();
            $table->enum('role', ['delivered_by', 'received_by']);
            $table->string('signer_name', 150);
            $table->string('signer_document', 50);
            $table->longText('signature_data');
            $table->timestamp('signed_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['movement_id', 'role'], 'uq_movement_role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_signatures');
    }
};
