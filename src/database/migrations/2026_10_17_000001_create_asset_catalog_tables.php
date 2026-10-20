<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos del módulo de activos: categorías (definen el tipo de activo)
 * y ubicaciones de dos niveles fijos Sede → Sitio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->enum('asset_type', ['biomedical', 'computing', 'general'])
                ->comment('biomedical/computing llevan hoja de vida y mantenimiento; general solo inventario');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_type', 'is_active'], 'idx_asset_categories_type_active');
        });

        Schema::create('asset_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique()->comment('Sede o edificio, ej. Clínica, Edificio 2');
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('asset_sites')->restrictOnDelete();
            $table->string('name', 120)->comment('Sitio dentro de la sede, ej. Consultorio 1, Call center');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'name'], 'uq_asset_places_site_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_places');
        Schema::dropIfExists('asset_sites');
        Schema::dropIfExists('asset_categories');
    }
};
