<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forge_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('vault_id')->nullable()->constrained('forge_vaults')->nullOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('source_key')->nullable();
            $table->unsignedInteger('input_count');
            $table->unsignedInteger('result_level');
            $table->decimal('result_combat_bonus', 6, 4);
            $table->unsignedBigInteger('result_cosmetic_id')->nullable();
            $table->unsignedBigInteger('result_pet_id')->nullable();
            $table->string('fee_currency', 20);
            $table->unsignedInteger('fee_amount');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['area_id', 'created_at']);
            $table->index(['student_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forge_ledgers');
    }
};
