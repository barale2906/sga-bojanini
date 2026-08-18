<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('supplier_type', ['inventory', 'expense', 'both'])
                ->default('both')
                ->after('is_active');
        });

        // Todos los proveedores preexistentes son de uso general
        DB::table('suppliers')->update(['supplier_type' => 'both']);
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('supplier_type');
        });
    }
};
