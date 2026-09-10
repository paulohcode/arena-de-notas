<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('game_event_answers')) {
            return;
        }

        Schema::create('game_event_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_event_attempt_id')->constrained('game_event_attempts')->cascadeOnDelete();
            $table->foreignId('game_event_question_id')->constrained('game_event_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('selected_index');
            $table->boolean('is_correct');
            $table->unsignedInteger('elapsed_ms');
            $table->timestamps();

            $table->unique(['game_event_attempt_id', 'game_event_question_id'], 'game_event_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_event_answers');
    }
};
