<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cosmetic_listings', function (Blueprint $table) {
            $table->foreignId('enrollment_cosmetic_id')
                ->nullable()
                ->after('enrollment_id')
                ->constrained('enrollment_cosmetics')
                ->cascadeOnDelete();
        });

        DB::table('cosmetic_listings')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $cosmeticId = DB::table('enrollment_cosmetics')
                    ->where('enrollment_id', $row->enrollment_id)
                    ->where('item_key', $row->item_key)
                    ->orderBy('id')
                    ->value('id');

                if ($cosmeticId) {
                    DB::table('cosmetic_listings')->where('id', $row->id)->update([
                        'enrollment_cosmetic_id' => $cosmeticId,
                    ]);
                }
            }
        });

        DB::table('cosmetic_listings')->whereNull('enrollment_cosmetic_id')->delete();

        Schema::table('cosmetic_listings', function (Blueprint $table) {
            $table->unique('enrollment_cosmetic_id');
        });
    }

    public function down(): void
    {
        Schema::table('cosmetic_listings', function (Blueprint $table) {
            $table->dropUnique(['enrollment_cosmetic_id']);
            $table->dropConstrainedForeignId('enrollment_cosmetic_id');
        });
    }
};
