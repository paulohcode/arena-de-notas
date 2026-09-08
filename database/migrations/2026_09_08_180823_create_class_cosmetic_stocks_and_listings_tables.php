<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_cosmetic_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('item_key');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['class_id', 'item_key']);
        });

        Schema::create('cosmetic_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('item_key');
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique(['enrollment_id', 'item_key']);
            $table->index(['class_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cosmetic_listings');
        Schema::dropIfExists('class_cosmetic_stocks');
    }
};
