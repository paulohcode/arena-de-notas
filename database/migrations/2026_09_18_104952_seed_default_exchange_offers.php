<?php

use App\Models\ExchangeRate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ExchangeRate::ensureShopOffers();
    }

    public function down(): void
    {
        //
    }
};
