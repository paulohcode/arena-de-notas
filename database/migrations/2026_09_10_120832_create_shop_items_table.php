<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->string('item_key')->unique();
            $table->string('slot', 30);
            $table->string('name', 60);
            $table->unsignedInteger('price');
            $table->string('currency', 20)->default('relics');
            $table->string('rarity', 20)->default('common');
            $table->string('icon', 32);
            $table->string('css', 30)->nullable();
            $table->string('label', 60)->nullable();
            $table->timestamps();

            $table->index('class_id');
            $table->index('slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_items');
    }
};
