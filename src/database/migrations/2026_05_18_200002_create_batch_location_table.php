<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['batch_id', 'location_id'], 'uq_batch_loc_batch_loc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_location');
    }
};
