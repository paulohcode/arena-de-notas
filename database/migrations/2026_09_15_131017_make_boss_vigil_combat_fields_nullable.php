<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE boss_vigils MODIFY seed BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE boss_vigils MODIFY log JSON NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE boss_vigils MODIFY seed BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE boss_vigils MODIFY log JSON NOT NULL');
    }
};
