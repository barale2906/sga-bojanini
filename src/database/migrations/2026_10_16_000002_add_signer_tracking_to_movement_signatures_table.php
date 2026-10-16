<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de cómo se firmó cada rol de un comprobante:
 *
 * - `pad`    → firma dibujada en el momento (flujo original, externos o
 *              usuarios sin firma registrada).
 * - `stored` → firma registrada del usuario autenticado que entrega.
 * - `pin`    → firma registrada de quien recibe, validada con su PIN.
 *
 * Las filas existentes quedan como `pad`, que es como se firmaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movement_signatures', function (Blueprint $table) {
            $table->enum('method', ['pad', 'stored', 'pin'])->default('pad')->after('role');
            $table->foreignId('user_id')->nullable()->after('method')->constrained('users')->nullOnDelete();
            $table->foreignId('user_signature_id')->nullable()->after('user_id')->constrained('user_signatures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movement_signatures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_signature_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('method');
        });
    }
};
