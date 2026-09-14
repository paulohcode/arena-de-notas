<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->string('boss_archetype', 40)->nullable()->after('description');
            $table->string('boss_difficulty', 20)->default('normal')->after('boss_archetype');
            $table->boolean('vigil_open')->default(false)->after('boss_difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['boss_archetype', 'boss_difficulty', 'vigil_open']);
        });
    }
};
