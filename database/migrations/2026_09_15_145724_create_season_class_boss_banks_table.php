<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('season_class_boss_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedInteger('relics')->default(0);
            $table->unsignedInteger('battles')->default(0);
            $table->unsignedSmallInteger('next_battle')->default(8);
            $table->unsignedTinyInteger('payout_percent')->default(20);
            $table->timestamps();

            $table->unique(['season_id', 'class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_class_boss_banks');
    }
};
