<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Ofertas padrão são garantidas em runtime via ExchangeRate::ensureShopOffers().
    }

    public function down(): void
    {
        //
    }
};
