<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_event_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_event_id')->constrained('game_events')->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->string('type', 30);
            $table->json('options');
            $table->unsignedTinyInteger('correct_index');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['game_event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_event_questions');
    }
};
