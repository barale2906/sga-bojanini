<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('approval_step_id')->constrained('approval_steps');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id'], 'idx_appr_rec_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_records');
    }
};
