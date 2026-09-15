<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boss_vigils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('resolved');
            $table->unsignedBigInteger('seed')->nullable();
            $table->json('log')->nullable();
            $table->boolean('won')->default(false);
            $table->boolean('mark_earned')->default(false);
            $table->unsignedSmallInteger('glory')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['season_id', 'class_id', 'student_id']);
            $table->index(['class_id', 'student_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boss_vigils');
    }
};
