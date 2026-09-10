<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('class_id')->constrained('areas')->cascadeOnDelete();
        });

        Schema::create('area_cosmetic_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->string('item_key');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['area_id', 'item_key']);
        });

        $now = now();
        $auraItems = DB::table('shop_items')->where('currency', 'auras')->get();

        foreach ($auraItems as $item) {
            $areaId = $item->area_id;

            if ($item->class_id) {
                $areaId = DB::table('classes')->where('id', $item->class_id)->value('area_id');
                DB::table('shop_items')->where('id', $item->id)->update([
                    'area_id' => $areaId,
                    'class_id' => null,
                ]);
            }

            $stocks = DB::table('class_cosmetic_stocks')
                ->join('classes', 'classes.id', '=', 'class_cosmetic_stocks.class_id')
                ->where('class_cosmetic_stocks.item_key', $item->item_key)
                ->select('classes.area_id', DB::raw('MAX(class_cosmetic_stocks.quantity) as quantity'))
                ->groupBy('classes.area_id')
                ->get();

            foreach ($stocks as $stock) {
                if (! $stock->area_id) {
                    continue;
                }

                if ($areaId && (int) $stock->area_id !== (int) $areaId) {
                    continue;
                }

                DB::table('area_cosmetic_stocks')->insert([
                    'area_id' => $stock->area_id,
                    'item_key' => $item->item_key,
                    'quantity' => (int) $stock->quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('class_cosmetic_stocks')->where('item_key', $item->item_key)->delete();
        }
    }

    public function down(): void
    {
        $stocks = DB::table('area_cosmetic_stocks')->get();

        foreach ($stocks as $stock) {
            $classIds = DB::table('classes')->where('area_id', $stock->area_id)->pluck('id');
            $now = now();

            foreach ($classIds as $classId) {
                DB::table('class_cosmetic_stocks')->insert([
                    'class_id' => $classId,
                    'item_key' => $stock->item_key,
                    'quantity' => $stock->quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $areaItems = DB::table('shop_items')->whereNotNull('area_id')->get();

        foreach ($areaItems as $item) {
            $classId = DB::table('classes')->where('area_id', $item->area_id)->orderBy('id')->value('id');
            DB::table('shop_items')->where('id', $item->id)->update([
                'class_id' => $classId,
                'area_id' => null,
            ]);
        }

        Schema::dropIfExists('area_cosmetic_stocks');

        Schema::table('shop_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
