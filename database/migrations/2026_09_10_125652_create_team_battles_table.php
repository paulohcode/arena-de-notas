<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('challenger_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('opponent_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('seed')->nullable();
            $table->json('log')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedSmallInteger('glory_winner')->default(0);
            $table->unsignedSmallInteger('glory_loser')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'status']);
            $table->index(['challenger_team_id', 'created_at']);
            $table->index(['opponent_team_id', 'created_at']);
            $table->index(['class_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_battles');
    }
};
