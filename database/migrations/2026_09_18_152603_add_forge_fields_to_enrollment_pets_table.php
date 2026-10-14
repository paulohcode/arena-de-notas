<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_pets', function (Blueprint $table) {
            $table->index('enrollment_id', 'enrollment_pets_enrollment_id_forge_idx');
        });

        Schema::table('enrollment_pets', function (Blueprint $table) {
            $table->dropUnique(['enrollment_id', 'pet_id']);
        });

        Schema::table('enrollment_pets', function (Blueprint $table) {
            $table->unsignedInteger('forge_level')->default(1)->after('aura_color');
            $table->decimal('combat_bonus', 6, 4)->nullable()->after('forge_level');
            $table->index(['enrollment_id', 'pet_id'], 'enrollment_pets_enrollment_pet_idx');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_pets', function (Blueprint $table) {
            $table->dropIndex('enrollment_pets_enrollment_pet_idx');
            $table->dropColumn(['forge_level', 'combat_bonus']);
        });

        Schema::table('enrollment_pets', function (Blueprint $table) {
            $table->unique(['enrollment_id', 'pet_id']);
        });

        Schema::table('enrollment_pets', function (Blueprint $table) {
            $table->dropIndex('enrollment_pets_enrollment_id_forge_idx');
        });
    }
};
