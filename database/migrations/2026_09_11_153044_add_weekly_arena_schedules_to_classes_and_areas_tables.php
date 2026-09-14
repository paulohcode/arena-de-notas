<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->json('arena_schedule')->nullable()->after('arena_daily_limit');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->json('realm_arena_schedule')->nullable()->after('realm_arena_daily_limit');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('arena_schedule');
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('realm_arena_schedule');
        });
    }
};
