<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('seed')->nullable();
            $table->json('log')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('glory_winner')->default(0);
            $table->unsignedSmallInteger('glory_loser')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['class_id', 'status']);
            $table->index(['challenger_id', 'created_at']);
            $table->index(['opponent_id', 'created_at']);
            $table->index(['class_id', 'challenger_id', 'opponent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duels');
    }
};
