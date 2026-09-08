<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedInteger('relics')->default(0)->after('glory');
            $table->string('equipped_frame')->nullable()->after('behavior_score');
            $table->string('equipped_accessory')->nullable()->after('equipped_frame');
            $table->string('equipped_title')->nullable()->after('equipped_accessory');
            $table->string('equipped_aura')->nullable()->after('equipped_title');
        });

        DB::table('enrollments')->update([
            'relics' => DB::raw('glory'),
        ]);

        Schema::create('enrollment_cosmetics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('item_key');
            $table->timestamps();

            $table->unique(['enrollment_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_cosmetics');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'relics',
                'equipped_frame',
                'equipped_accessory',
                'equipped_title',
                'equipped_aura',
            ]);
        });
    }
};
