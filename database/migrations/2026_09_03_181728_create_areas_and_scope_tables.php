<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 20)->default('#c2410c');
            $table->string('emblem', 30)->default('castle');
            $table->unsignedTinyInteger('map_x')->default(50);
            $table->unsignedTinyInteger('map_y')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('area_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['area_id', 'user_id']);
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('teacher_id')->constrained('areas')->restrictOnDelete();
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->after('id')->constrained('areas')->restrictOnDelete();
        });

        $now = now();
        $defaultAreaId = DB::table('areas')->insertGetId([
            'name' => 'Reino Central',
            'slug' => 'reino-central',
            'description' => 'Área padrão do sistema.',
            'color' => '#c2410c',
            'emblem' => 'castle',
            'map_x' => 50,
            'map_y' => 48,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('classes')->whereNull('area_id')->update(['area_id' => $defaultAreaId]);
        DB::table('seasons')->whereNull('area_id')->update(['area_id' => $defaultAreaId]);

        $teacherIds = DB::table('users')->where('role', 'teacher')->pluck('id');
        foreach ($teacherIds as $teacherId) {
            DB::table('area_user')->insert([
                'area_id' => $defaultAreaId,
                'user_id' => $teacherId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });

        Schema::dropIfExists('area_user');
        Schema::dropIfExists('areas');
    }
};
