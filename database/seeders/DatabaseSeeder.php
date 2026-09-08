<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\GameLoopService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(BadgeSeeder::class);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@arena.local'],
            [
                'name' => 'Administrador',
                'password' => 'Admin@123',
                'role' => 'admin',
                'must_change_password' => false,
            ]
        );

        // Garante conta de admin mesmo em re-seed.
        $admin->forceFill(['role' => 'admin'])->save();

        $systems = Area::query()->updateOrCreate(
            ['slug' => 'sistemas'],
            [
                'name' => 'Sistemas',
                'description' => 'Reino da área de Sistemas — código, redes e batalhas digitais.',
                'color' => '#c2410c',
                'emblem' => 'gear',
                'map_x' => 32,
                'map_y' => 42,
                'is_active' => true,
            ]
        );

        $mechanics = Area::query()->updateOrCreate(
            ['slug' => 'mecanica'],
            [
                'name' => 'Mecânica',
                'description' => 'Reino da Mecânica — forjas, motores e precisão.',
                'color' => '#92400e',
                'emblem' => 'forge',
                'map_x' => 68,
                'map_y' => 55,
                'is_active' => true,
            ]
        );

        // Migração antiga criava "Reino Central"; renomeamos se existir.
        Area::query()->where('slug', 'reino-central')->update([
            'name' => 'Sistemas',
            'slug' => 'sistemas-legado',
            'is_active' => false,
        ]);

        $teacher = User::query()->updateOrCreate(
            ['email' => 'admin@escola.local'],
            [
                'name' => 'Professor Sistemas',
                'password' => 'Professor@123',
                'role' => 'teacher',
                'must_change_password' => false,
            ]
        );

        $teacher->areas()->syncWithoutDetaching([$systems->id]);

        $class = SchoolClass::query()->updateOrCreate(
            ['teacher_id' => $teacher->id, 'name' => 'Sistemas — Turma Demo'],
            [
                'area_id' => $systems->id,
                'year' => '2026',
                'score_mode' => 'up_from_zero',
                'team_grade_weight' => 1,
                'behavior_grade_weight' => 1,
                'attendance_grade_weight' => 1,
            ]
        );

        $students = collect([
            ['name' => 'Ana Souza', 'email' => 'ana@escola.local', 'character_class' => 'feiticeira', 'character_name' => 'Luna Arcana', 'character_avatar' => 'lua'],
            ['name' => 'Bruno Lima', 'email' => 'bruno@escola.local', 'character_class' => 'guerreiro', 'character_name' => 'Escudo de Ferro', 'character_avatar' => 'elmo'],
            ['name' => 'Carla Nunes', 'email' => 'carla@escola.local', 'character_class' => 'arqueiro', 'character_name' => 'Seta Veloz', 'character_avatar' => 'aguia'],
            ['name' => 'Diego Alves', 'email' => 'diego@escola.local', 'character_class' => 'mago', 'character_name' => 'Cristal Negro', 'character_avatar' => 'cristal'],
        ])->map(function (array $data) {
            return User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'aluno123',
                    'role' => 'student',
                    'must_change_password' => true,
                    'character_class' => $data['character_class'],
                    'character_name' => $data['character_name'],
                    'character_avatar' => $data['character_avatar'],
                    'character_approval_status' => 'approved',
                ]
            );
        });

        foreach ($students as $student) {
            $class->students()->syncWithoutDetaching([$student->id => [
                'ranking_visible' => $student->email !== 'diego@escola.local',
                'xp' => 0,
                'behavior_score' => 100,
            ]]);
        }

        $dragons = $class->teams()->updateOrCreate(
            ['name' => 'Dragões de Código'],
            ['color' => '#f97316', 'emblem' => 'dragon']
        );
        $owls = $class->teams()->updateOrCreate(
            ['name' => 'Corujas Noturnas'],
            ['color' => '#6366f1', 'emblem' => 'owl']
        );

        $dragons->members()->sync([$students[0]->id, $students[1]->id]);
        $owls->members()->sync([$students[2]->id, $students[3]->id]);

        $a1 = $class->activities()->updateOrCreate(['name' => 'Atividade 01'], ['type' => 'individual', 'max_score' => 100, 'weight' => 1]);
        $a2 = $class->activities()->updateOrCreate(['name' => 'Atividade 02'], ['type' => 'individual', 'max_score' => 100, 'weight' => 1]);
        $a3 = $class->activities()->updateOrCreate(['name' => 'Atividade 03'], ['type' => 'individual', 'max_score' => 100, 'weight' => 1]);
        $teamAct = $class->activities()->updateOrCreate(['name' => 'Desafio da Guilda'], ['type' => 'team', 'max_score' => 100, 'weight' => 1]);
        $a5 = $class->activities()->updateOrCreate(['name' => 'Atividade 05'], ['type' => 'individual', 'max_score' => 100, 'weight' => 1]);

        $loop = app(GameLoopService::class);

        $individual = [
            [$students[0], $a1, 92],
            [$students[0], $a2, 88],
            [$students[0], $a3, 95],
            [$students[0], $a5, 90],
            [$students[1], $a1, 70],
            [$students[1], $a2, 75],
            [$students[1], $a3, 68],
            [$students[1], $a5, 80],
            [$students[2], $a1, 85],
            [$students[2], $a2, 90],
            [$students[2], $a3, 87],
            [$students[2], $a5, 93],
            [$students[3], $a1, 60],
            [$students[3], $a2, 55],
            [$students[3], $a3, 72],
            [$students[3], $a5, 64],
        ];

        foreach ($individual as [$student, $activity, $score]) {
            $loop->recordActivityGrade($class, $activity, $score, $teacher, student: $student);
        }

        $loop->recordActivityGrade($class, $teamAct, 94, $teacher, team: $dragons);
        $loop->recordActivityGrade($class, $teamAct, 81, $teacher, team: $owls);
        $loop->recordAdjustment($class, -10, 'Não se comportou', $teacher, student: $students[1]);
        $loop->recordAdjustment($class, 15, 'Atividade extra', $teacher, student: $students[1]);
    }
}
