<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realm_duels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('challenger_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('opponent_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('challenger_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opponent_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('seed')->nullable();
            $table->json('log')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('aura_winner')->default(0);
            $table->unsignedSmallInteger('aura_loser')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['area_id', 'status']);
            $table->index(['challenger_id', 'created_at']);
            $table->index(['opponent_id', 'created_at']);
            $table->index(['area_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realm_duels');
    }
};
