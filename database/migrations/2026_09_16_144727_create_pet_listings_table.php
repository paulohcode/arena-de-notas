<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pet_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('enrollment_pet_id')->constrained('enrollment_pets')->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique('enrollment_pet_id');
            $table->index(['class_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_listings');
    }
};
