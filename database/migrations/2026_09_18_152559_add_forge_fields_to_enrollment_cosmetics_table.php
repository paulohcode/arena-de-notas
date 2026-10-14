<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_cosmetics', function (Blueprint $table) {
            $table->index('enrollment_id', 'enrollment_cosmetics_enrollment_id_forge_idx');
        });

        Schema::table('enrollment_cosmetics', function (Blueprint $table) {
            $table->dropUnique(['enrollment_id', 'item_key']);
        });

        Schema::table('enrollment_cosmetics', function (Blueprint $table) {
            $table->unsignedInteger('forge_level')->default(1)->after('item_key');
            $table->decimal('combat_bonus', 6, 4)->nullable()->after('forge_level');
            $table->string('forge_tone', 32)->nullable()->after('combat_bonus');
            $table->index(['enrollment_id', 'item_key'], 'enrollment_cosmetics_enrollment_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_cosmetics', function (Blueprint $table) {
            $table->dropIndex('enrollment_cosmetics_enrollment_item_idx');
            $table->dropColumn(['forge_level', 'combat_bonus', 'forge_tone']);
        });

        Schema::table('enrollment_cosmetics', function (Blueprint $table) {
            $table->unique(['enrollment_id', 'item_key']);
        });

        Schema::table('enrollment_cosmetics', function (Blueprint $table) {
            $table->dropIndex('enrollment_cosmetics_enrollment_id_forge_idx');
        });
    }
};
