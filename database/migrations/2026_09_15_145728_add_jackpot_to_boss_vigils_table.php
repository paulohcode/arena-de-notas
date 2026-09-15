<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boss_vigils', function (Blueprint $table) {
            $table->json('jackpot')->nullable()->after('loot');
        });
    }

    public function down(): void
    {
        Schema::table('boss_vigils', function (Blueprint $table) {
            $table->dropColumn('jackpot');
        });
    }
};
