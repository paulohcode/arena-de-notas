<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->unique()->constrained('areas')->cascadeOnDelete();
            $table->unsignedInteger('relics')->default(0);
            $table->unsignedInteger('seals')->default(0);
            $table->unsignedInteger('auras')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_vaults');
    }
};
