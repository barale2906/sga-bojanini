<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_service_id')
                  ->constrained('medical_services')
                  ->cascadeOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['medical_service_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_prices');
    }
};
