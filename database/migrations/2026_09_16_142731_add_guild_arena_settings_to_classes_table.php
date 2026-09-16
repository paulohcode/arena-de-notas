<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('guild_arena_daily_limit')->default(1)->after('arena_schedule');
            $table->json('guild_arena_schedule')->nullable()->after('guild_arena_daily_limit');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['guild_arena_daily_limit', 'guild_arena_schedule']);
        });
    }
};
