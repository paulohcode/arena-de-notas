<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_a', 20);
            $table->string('currency_b', 20);
            $table->unsignedInteger('amount_a');
            $table->unsignedInteger('amount_b');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['currency_a', 'currency_b']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
