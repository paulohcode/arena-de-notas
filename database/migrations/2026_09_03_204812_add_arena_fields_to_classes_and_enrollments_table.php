<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->boolean('arena_open')->default(false)->after('behavior_grade_weight');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('glory')->default(0)->after('xp');
            $table->unsignedInteger('arena_wins')->default(0)->after('glory');
            $table->unsignedInteger('arena_losses')->default(0)->after('arena_wins');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('arena_open');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['glory', 'arena_wins', 'arena_losses']);
        });
    }
};
