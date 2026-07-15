<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sanitary_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('registration_number', 100);
            $table->date('expiry_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_variant_id', 'registration_number'], 'uq_variant_registration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sanitary_registrations');
    }
};
