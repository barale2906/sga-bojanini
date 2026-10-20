<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activos, traslados (firma obligatoria solo cuando el activo sale del
 * edificio), historial de ubicaciones, comentarios acumulables y hoja de vida técnica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->char('code', 6)->unique()->comment('Código interno secuencial 000001–999999 (Code128)');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignId('current_place_id')->nullable()->constrained('asset_places')->restrictOnDelete()
                ->comment('Sitio vigente; null mientras está fuera de las instalaciones');
            $table->boolean('is_off_premises')->default(false)->comment('Fuera de las instalaciones (reparación externa, préstamo, baja)');
            $table->string('off_premises_location', 200)->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('serial', 100)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_value', 15, 2)->nullable()->comment('Informativo; la depreciación la maneja contabilidad');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->date('warranty_until')->nullable();
            $table->enum('status', ['operational', 'maintenance', 'out_of_service', 'available', 'retired'])->default('operational');
            $table->date('retired_at')->nullable();
            $table->text('retirement_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_assets_status');
            $table->index('serial', 'idx_assets_serial');
        });

        Schema::create('asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->enum('type', ['relocation', 'site_transfer', 'external_repair', 'loan', 'return', 'retirement']);
            $table->enum('status', ['pending_signature', 'confirmed', 'cancelled']);
            $table->boolean('requires_signature')->default(false);
            $table->foreignId('from_place_id')->nullable()->constrained('asset_places')->nullOnDelete();
            $table->foreignId('to_place_id')->nullable()->constrained('asset_places')->nullOnDelete();
            $table->string('from_external_location', 200)->nullable();
            $table->string('external_destination', 200)->nullable()->comment('Taller, entidad o destino fuera de las instalaciones');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('from_responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_return_date')->nullable();
            $table->string('resulting_status', 20)->nullable()->comment('Estado que toma el activo al aplicarse (retorno)');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status'], 'idx_asset_transfers_asset_status');
            $table->index('status', 'idx_asset_transfers_status');
        });

        Schema::create('asset_location_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('asset_places')->restrictOnDelete();
            $table->string('external_location', 200)->nullable()->comment('Destino cuando el activo sale de las instalaciones');
            $table->dateTime('from_date');
            $table->dateTime('to_date')->nullable()->comment('null = ubicación vigente');
            $table->foreignId('transfer_id')->nullable()->constrained('asset_transfers')->nullOnDelete()->comment('Traslado que originó el tramo');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();

            $table->index(['asset_id', 'to_date'], 'idx_asset_location_current');
        });

        Schema::create('asset_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->text('comment');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['asset_id', 'created_at'], 'idx_asset_comments_asset');
        });

        Schema::create('asset_technical_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->unique()->constrained('assets')->cascadeOnDelete();

            // Comunes
            $table->string('manufacturer', 150)->nullable();
            $table->string('country_of_origin', 80)->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->unsignedTinyInteger('useful_life_years')->nullable();
            $table->enum('acquisition_mode', ['purchase', 'donation', 'comodato', 'leasing'])->nullable();
            $table->json('technical_specs')->nullable()->comment('Especificaciones libres clave → valor');
            $table->text('accessories')->nullable();
            $table->text('usage_recommendations')->nullable();

            // Biomédicos
            $table->string('invima_registry', 80)->nullable();
            $table->enum('risk_class', ['I', 'IIa', 'IIb', 'III'])->nullable();
            $table->enum('biomedical_class', ['diagnosis', 'treatment', 'rehabilitation', 'prevention', 'laboratory'])->nullable();
            $table->enum('power_source', ['electric', 'battery', 'pneumatic', 'mechanical', 'other'])->nullable();
            $table->string('voltage', 40)->nullable();
            $table->string('current', 40)->nullable();
            $table->string('power', 40)->nullable();
            $table->string('frequency', 40)->nullable();
            $table->boolean('requires_calibration')->default(false);
            $table->text('operating_conditions')->nullable();

            // Cómputo
            $table->string('processor', 120)->nullable();
            $table->string('ram', 40)->nullable();
            $table->string('storage', 80)->nullable();
            $table->string('operating_system', 80)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('hostname', 80)->nullable();
            $table->json('licenses')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_technical_profiles');
        Schema::dropIfExists('asset_comments');
        Schema::dropIfExists('asset_location_history');
        Schema::dropIfExists('asset_transfers');
        Schema::dropIfExists('assets');
    }
};
