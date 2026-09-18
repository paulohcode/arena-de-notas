<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->foreignId('arranged_by')
                ->nullable()
                ->after('opponent_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('arena_weekly_quota')
                ->default(2)
                ->after('guild_arena_schedule');
            $table->string('arena_quota_settled_week', 8)
                ->nullable()
                ->after('arena_weekly_quota');
        });
    }

    public function down(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('arranged_by');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['arena_weekly_quota', 'arena_quota_settled_week']);
        });
    }
};
