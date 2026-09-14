<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_class_rites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('boss_max_hp')->default(0);
            $table->unsignedInteger('boss_hp')->default(0);
            $table->unsignedInteger('marks_applied')->default(0);
            $table->unsignedBigInteger('seed')->nullable();
            $table->json('log')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_class_rites');
    }
};
