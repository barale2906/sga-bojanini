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
            $table->string('billing_status', 20)->nullable()->after('approved_at');
            $table->unsignedBigInteger('billed_by_user_id')->nullable()->after('billing_status');
            $table->timestamp('billed_at')->nullable()->after('billed_by_user_id');

            $table->foreign('billed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patient_procedure_records', function (Blueprint $table) {
            $table->dropForeign(['billed_by_user_id']);
            $table->dropColumn(['billing_status', 'billed_by_user_id', 'billed_at']);
        });
    }
};
