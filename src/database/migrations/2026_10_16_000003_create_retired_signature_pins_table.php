<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuarentena de PIN de firma liberados.
 *
 * Cuando un usuario regenera su PIN (normalmente porque cree que alguien lo
 * conoce), el PIN anterior no se puede asignar a otro usuario hasta
 * `available_at`. Así, quien conocía el PIN viejo no termina firmando, sin
 * saberlo, a nombre de otra persona.
 *
 * Solo se guarda el HMAC del PIN, igual que en `user_signatures`. Un mismo PIN
 * puede retirarse varias veces a lo largo del tiempo, por eso no es único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_signature_pins', function (Blueprint $table) {
            $table->id();
            $table->string('pin_lookup', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->useCurrent();
            $table->timestamp('available_at');

            $table->index(['pin_lookup', 'available_at'], 'idx_retired_pins_lookup_available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_signature_pins');
    }
};
