<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('key', 20)->unique();
            $table->string('name', 40);
            $table->string('icon', 32);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->timestamps();
        });

        $now = now();

        DB::table('game_currencies')->insert([
            [
                'key' => 'relics',
                'name' => 'Relíquias',
                'icon' => '💠',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'seals',
                'name' => 'Selos',
                'icon' => '💮',
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'auras',
                'name' => 'Aura',
                'icon' => '✨',
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'glory',
                'name' => 'Glória',
                'icon' => '🏆',
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('game_currencies');
    }
};
