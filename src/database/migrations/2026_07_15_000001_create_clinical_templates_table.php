<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_service_id')->constrained('medical_services')->cascadeOnDelete();
            $table->string('title', 200);
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_templates');
    }
};
