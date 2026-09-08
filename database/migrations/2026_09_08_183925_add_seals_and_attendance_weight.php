<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('seals')->default(0)->after('relics');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('attendance_grade_weight')->default(1)->after('behavior_grade_weight');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('seals');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('attendance_grade_weight');
        });
    }
};
