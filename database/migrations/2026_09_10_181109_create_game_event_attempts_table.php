<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('game_event_attempts')) {
            return;
        }

        Schema::create('game_event_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_event_id')->constrained('game_events')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('correct_time_ms')->default(0);
            $table->unsignedInteger('current_question_position')->nullable();
            $table->timestamp('current_question_shown_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->boolean('rewards_granted')->default(false);
            $table->timestamps();

            $table->unique(['game_event_id', 'student_id']);
            $table->index(['game_event_id', 'correct_count', 'correct_time_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_event_attempts');
    }
};
