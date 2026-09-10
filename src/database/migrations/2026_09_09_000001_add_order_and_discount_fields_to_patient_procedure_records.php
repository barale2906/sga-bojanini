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
            $table->string('order_number', 30)->nullable()->after('movement_document_id')->index();
            $table->string('discount_type', 15)->nullable()->after('order_number');
            $table->decimal('discount_value', 12, 4)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 14, 2)->nullable()->after('discount_value');
            $table->decimal('net_total', 14, 2)->nullable()->after('discount_amount');
            $table->string('discount_status', 20)->nullable()->after('net_total');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('discount_status');
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->after('created_by_user_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->string('patient_email', 100)->nullable()->after('patient_last_name');
            $table->string('patient_address', 150)->nullable()->after('patient_email');
            $table->string('patient_phone', 50)->nullable()->after('patient_address');

            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patient_procedure_records', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropIndex(['order_number']);
            $table->dropColumn([
                'order_number', 'discount_type', 'discount_value', 'discount_amount',
                'net_total', 'discount_status', 'created_by_user_id', 'approved_by_user_id',
                'approved_at', 'patient_email', 'patient_address', 'patient_phone',
            ]);
        });
    }
};
