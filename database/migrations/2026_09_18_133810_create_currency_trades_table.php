<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('vault_id')->nullable()->constrained('exchange_vaults')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained('currency_trade_listings')->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('offer_currency', 20);
            $table->unsignedInteger('offer_amount');
            $table->string('ask_currency', 20);
            $table->unsignedInteger('ask_amount');
            $table->string('fee_currency', 20);
            $table->unsignedInteger('fee_amount');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_trades');
    }
};
