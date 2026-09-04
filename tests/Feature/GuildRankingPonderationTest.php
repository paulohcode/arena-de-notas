<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuildRankingPonderationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guild_score_ponders_guild_activity_with_members_individual_average(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
        ]);

        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma X']);

        $ind = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Atividade Individual',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);
        $teamAct = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Atividade Equipe',
            'type' => 'team',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $s1 = User::factory()->create(['role' => 'student', 'name' => 'Aluno 1']);
        $s2 = User::factory()->create(['role' => 'student', 'name' => 'Aluno 2']);

        $class->students()->attach($s1->id, ['ranking_visible' => true, 'xp' => 0]);
        $class->students()->attach($s2->id, ['ranking_visible' => true, 'xp' => 0]);

        /** @var Team $team */
        $team = $class->teams()->create([
            'name' => 'Guilda Dragão',
            'color' => '#7c3aed',
            'emblem' => 'dragon',
        ]);
        $team->members()->sync([$s1->id, $s2->id]);

        // Componente A (guilda): 90
        LedgerEntry::query()->create([
            'class_id' => $class->id,
            'activity_id' => $teamAct->id,
            'student_id' => null,
            'team_id' => $team->id,
            'created_by' => $teacher->id,
            'type' => 'activity',
            'raw_score' => 90,
            'delta' => 90,
            'reason' => 'Atividade Equipe',
        ]);

        // Componente B (média individual dos membros sem "nota equipe"): 50
        foreach ([$s1, $s2] as $student) {
            LedgerEntry::query()->create([
                'class_id' => $class->id,
                'activity_id' => $ind->id,
                'student_id' => $student->id,
                'team_id' => null,
                'created_by' => $teacher->id,
                'type' => 'activity',
                'raw_score' => 50,
                'delta' => 50,
                'reason' => 'Atividade Individual',
            ]);
        }

        $ranking = app(RankingService::class);
        $guilds = $ranking->guilds($class);

        $this->assertNotEmpty($guilds);
        $this->assertSame(1, $guilds[0]['position']);
        // Guilda 90 + média individual dos membros (50 prova + 100 comportamento) / 2 = 75
        // Score final = (90 + 75) / 2 = 82.5
        $this->assertEquals(82.5, $guilds[0]['score'], 0.01);
    }
}
