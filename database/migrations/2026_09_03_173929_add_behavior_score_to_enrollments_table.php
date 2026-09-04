<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->decimal('behavior_score', 5, 2)->default(100)->after('xp');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('behavior_grade_weight')->default(1)->after('team_grade_weight');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('behavior_score');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('behavior_grade_weight');
        });
    }
};
