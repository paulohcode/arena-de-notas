<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedInteger('arena_cooldown_minutes')->default(120)->after('arena_open');
            $table->unsignedTinyInteger('arena_daily_limit')->default(3)->after('arena_cooldown_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['arena_cooldown_minutes', 'arena_daily_limit']);
        });
    }
};
