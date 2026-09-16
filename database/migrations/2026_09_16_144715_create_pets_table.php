<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('species_key', 60)->nullable();
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            $table->string('rarity', 20)->default('common');
            $table->string('sprite_key', 40)->nullable();
            $table->string('gif_path')->nullable();
            $table->unsignedInteger('price_relics')->default(0);
            $table->unsignedInteger('price_seals')->default(0);
            $table->unsignedInteger('price_auras')->default(0);
            $table->float('combat_bonus')->default(0.02);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['class_id', 'species_key']);
            $table->index(['class_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
