<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_procedure_records', function (Blueprint $table) {
            $table->foreignId('movement_document_id')
                ->nullable()
                ->after('medical_service_id')
                ->constrained('movement_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patient_procedure_records', function (Blueprint $table) {
            $table->dropForeign(['movement_document_id']);
            $table->dropColumn('movement_document_id');
        });
    }
};
