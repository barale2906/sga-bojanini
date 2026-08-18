<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('order_type', ['inventory', 'expense'])->default('inventory')->after('id');
            $table->foreignId('warehouse_id')->nullable()->change();
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('total_amount');
            $table->decimal('amount_paid', 14, 2)->default(0)->after('payment_status');
            $table->string('invoice_number', 100)->nullable()->after('amount_paid');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->timestamp('accounting_sent_at')->nullable()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'payment_status', 'amount_paid', 'invoice_number', 'invoice_date', 'accounting_sent_at']);
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });
    }
};
