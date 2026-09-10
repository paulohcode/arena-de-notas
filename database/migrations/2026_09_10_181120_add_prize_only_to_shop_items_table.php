<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_items', 'prize_only')) {
            return;
        }

        Schema::table('shop_items', function (Blueprint $table) {
            $table->boolean('prize_only')->default(false)->after('combat_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->dropColumn('prize_only');
        });
    }
};
