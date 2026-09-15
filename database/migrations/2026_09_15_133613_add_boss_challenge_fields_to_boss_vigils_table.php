<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boss_vigils', function (Blueprint $table) {
            $table->unsignedSmallInteger('fee_relics')->default(0)->after('glory');
            $table->json('loot')->nullable()->after('fee_relics');
        });
    }

    public function down(): void
    {
        Schema::table('boss_vigils', function (Blueprint $table) {
            $table->dropColumn(['fee_relics', 'loot']);
        });
    }
};
