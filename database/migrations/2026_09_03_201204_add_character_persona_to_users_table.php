<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('character_name', 24)->nullable()->after('character_class');
            $table->string('character_avatar', 30)->nullable()->after('character_name');
            $table->string('pending_character_name', 24)->nullable()->after('character_avatar');
            $table->string('pending_character_avatar', 30)->nullable()->after('pending_character_name');
            $table->string('character_approval_status', 20)->default('none')->after('pending_character_avatar');
            $table->string('character_rejection_reason', 200)->nullable()->after('character_approval_status');
            $table->index('character_approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['character_approval_status']);
            $table->dropColumn([
                'character_name',
                'character_avatar',
                'pending_character_name',
                'pending_character_avatar',
                'character_approval_status',
                'character_rejection_reason',
            ]);
        });
    }
};
