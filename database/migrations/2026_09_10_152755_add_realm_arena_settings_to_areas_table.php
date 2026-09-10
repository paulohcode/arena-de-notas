<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('realm_arena_open')->default(true)->after('is_active');
            $table->unsignedInteger('realm_arena_cooldown_minutes')->default(0)->after('realm_arena_open');
            $table->unsignedTinyInteger('realm_arena_daily_limit')->default(3)->after('realm_arena_cooldown_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn([
                'realm_arena_open',
                'realm_arena_cooldown_minutes',
                'realm_arena_daily_limit',
            ]);
        });
    }
};
