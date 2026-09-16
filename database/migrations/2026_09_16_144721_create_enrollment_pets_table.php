<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained('pets')->cascadeOnDelete();
            $table->string('custom_name', 20);
            $table->string('aura_color', 20);
            $table->timestamps();

            $table->unique(['enrollment_id', 'pet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_pets');
    }
};
