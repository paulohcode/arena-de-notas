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
            $table->string('pay_currency', 20);
            $table->string('receive_currency', 20);
            $table->unsignedInteger('pay_amount');
            $table->unsignedInteger('receive_amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['pay_currency', 'receive_currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
