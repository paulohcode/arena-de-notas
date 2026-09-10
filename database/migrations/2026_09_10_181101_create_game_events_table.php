<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('kind', 20);
            $table->string('mode', 20);
            $table->string('status', 20)->default('draft');
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('question_seconds')->default(30);
            $table->unsignedInteger('current_question_index')->nullable();
            $table->timestamp('current_question_opened_at')->nullable();
            $table->unsignedInteger('relics_per_correct')->default(0);
            $table->unsignedInteger('seals_per_correct')->default(0);
            $table->unsignedInteger('auras_per_correct')->default(0);
            $table->foreignId('prize_item_id')->nullable()->constrained('shop_items')->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
            $table->index(['class_id', 'status']);
            $table->index(['area_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_events');
    }
};
