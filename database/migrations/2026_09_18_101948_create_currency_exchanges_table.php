<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates')->nullOnDelete();
            $table->string('pay_currency', 20);
            $table->string('receive_currency', 20);
            $table->unsignedInteger('pay_amount');
            $table->unsignedInteger('receive_amount');
            $table->unsignedInteger('lots');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};
