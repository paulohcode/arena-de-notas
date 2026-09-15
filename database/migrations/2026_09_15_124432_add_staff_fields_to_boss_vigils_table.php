<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boss_vigils', function (Blueprint $table) {
            $table->string('source', 20)->default('student')->after('student_id');
            $table->foreignId('initiated_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->index(['season_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('boss_vigils', function (Blueprint $table) {
            $table->dropIndex(['season_id', 'source']);
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropColumn('source');
        });
    }
};
