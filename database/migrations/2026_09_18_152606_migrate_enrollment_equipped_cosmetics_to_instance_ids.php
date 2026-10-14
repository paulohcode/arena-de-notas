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
            $table->foreignId('equipped_frame_id')->nullable()->after('equipped_aura')->constrained('enrollment_cosmetics')->nullOnDelete();
            $table->foreignId('equipped_accessory_id')->nullable()->after('equipped_frame_id')->constrained('enrollment_cosmetics')->nullOnDelete();
            $table->foreignId('equipped_title_id')->nullable()->after('equipped_accessory_id')->constrained('enrollment_cosmetics')->nullOnDelete();
            $table->foreignId('equipped_aura_id')->nullable()->after('equipped_title_id')->constrained('enrollment_cosmetics')->nullOnDelete();
        });

        $slots = [
            'frame' => 'equipped_frame',
            'accessory' => 'equipped_accessory',
            'title' => 'equipped_title',
            'aura' => 'equipped_aura',
        ];

        DB::table('enrollments')->orderBy('id')->chunkById(100, function ($rows) use ($slots): void {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($slots as $slot => $oldColumn) {
                    $itemKey = $row->{$oldColumn} ?? null;
                    if (! filled($itemKey)) {
                        continue;
                    }

                    $cosmeticId = DB::table('enrollment_cosmetics')
                        ->where('enrollment_id', $row->id)
                        ->where('item_key', $itemKey)
                        ->orderBy('id')
                        ->value('id');

                    if ($cosmeticId) {
                        $updates['equipped_'.$slot.'_id'] = $cosmeticId;
                    }
                }

                if ($updates !== []) {
                    DB::table('enrollments')->where('id', $row->id)->update($updates);
                }
            }
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'equipped_frame',
                'equipped_accessory',
                'equipped_title',
                'equipped_aura',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('equipped_frame')->nullable()->after('behavior_score');
            $table->string('equipped_accessory')->nullable()->after('equipped_frame');
            $table->string('equipped_title')->nullable()->after('equipped_accessory');
            $table->string('equipped_aura')->nullable()->after('equipped_title');
        });

        $slots = ['frame', 'accessory', 'title', 'aura'];

        DB::table('enrollments')->orderBy('id')->chunkById(100, function ($rows) use ($slots): void {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($slots as $slot) {
                    $id = $row->{'equipped_'.$slot.'_id'} ?? null;
                    if (! $id) {
                        continue;
                    }

                    $itemKey = DB::table('enrollment_cosmetics')->where('id', $id)->value('item_key');
                    if ($itemKey) {
                        $updates['equipped_'.$slot] = $itemKey;
                    }
                }

                if ($updates !== []) {
                    DB::table('enrollments')->where('id', $row->id)->update($updates);
                }
            }
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipped_frame_id');
            $table->dropConstrainedForeignId('equipped_accessory_id');
            $table->dropConstrainedForeignId('equipped_title_id');
            $table->dropConstrainedForeignId('equipped_aura_id');
        });
    }
};
