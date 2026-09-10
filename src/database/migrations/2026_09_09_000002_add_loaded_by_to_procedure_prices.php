<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedure_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('loaded_by_user_id')->nullable()->after('notes');
            $table->foreign('loaded_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procedure_prices', function (Blueprint $table) {
            $table->dropForeign(['loaded_by_user_id']);
            $table->dropColumn('loaded_by_user_id');
        });
    }
};
