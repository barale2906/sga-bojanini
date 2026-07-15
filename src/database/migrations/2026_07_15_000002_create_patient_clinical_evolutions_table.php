<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_clinical_evolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_procedure_record_id')->constrained('patient_procedure_records')->cascadeOnDelete();
            $table->longText('content');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_clinical_evolutions');
    }
};
