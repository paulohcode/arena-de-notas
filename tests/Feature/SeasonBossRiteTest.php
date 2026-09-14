<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\BossVigil;
use App\Models\Enrollment;
use App\Models\Season;
use App\Models\SeasonClassRite;
use App\Models\User;
use App\Services\ArenaCombatService;
use App\Services\BossRiteService;
use App\Services\GameLoopService;
use App\Support\BossArchetypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonBossRiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_assign_boss_archetype_to_season(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.seasons.store'), [
                'area_id' => $class->area_id,
                'name' => '1º Trimestre',
                'description' => 'Rito de abertura',
                'boss_archetype' => 'eclipse',
                'boss_difficulty' => 'hard',
                'class_ids' => [$class->id],
            ])
            ->assertRedirect(route('teacher.seasons.index'));

        $season = Season::query()->first();
        $this->assertNotNull($season);
        $this->assertSame('eclipse', $season->boss_archetype);
        $this->assertSame('hard', $season->boss_difficulty);
        $this->assertFalse($season->vigil_open);
        $this->assertTrue($season->hasBoss());
    }

    public function test_shadow_vigil_awards_mark_and_glory_on_win(): void
    {
        [$teacher, $class, $student, $season] = $this->readyBossSeason(grade: 98, difficulty: 'easy');

        $this->actingAs($teacher)
            ->post(route('teacher.seasons.vigil.open', $season))
            ->assertRedirect();

        $this->assertTrue($season->fresh()->vigil_open);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.vigil.challenge', $season))
            ->assertRedirect();

        $vigil = BossVigil::query()->first();
        $this->assertNotNull($vigil);
        $this->assertSame($student->id, $vigil->student_id);
        $this->assertNotEmpty($vigil->log['turns']);
        $this->assertArrayHasKey('phase', $vigil->log['turns'][0]);

        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertGreaterThan(0, (int) $enrollment->glory);
        $this->assertSame((int) $enrollment->glory, (int) $enrollment->relics);

        if ($vigil->won) {
            $this->assertTrue($vigil->mark_earned);
            $this->assertSame(1, app(BossRiteService::class)->markCount($season, $class));
        }
    }

    public function test_vigil_daily_limit_blocks_second_attempt(): void
    {
        [$teacher, $class, $student, $season] = $this->readyBossSeason(grade: 80, difficulty: 'easy');
        app(BossRiteService::class)->setVigilOpen($season, true);

        BossVigil::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => BossVigil::STATUS_RESOLVED,
            'seed' => 1,
            'log' => ['turns' => [], 'winner_id' => $student->id],
            'won' => true,
            'mark_earned' => true,
            'glory' => 10,
            'resolved_at' => now(),
        ]);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.vigil.challenge', $season))
            ->assertSessionHasErrors('vigil');
    }

    public function test_rite_uses_marks_to_weaken_shared_boss_hp(): void
    {
        [$teacher, $class, $student, $season] = $this->readyBossSeason(grade: 90, difficulty: 'easy');
        $rites = app(BossRiteService::class);

        BossVigil::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'status' => BossVigil::STATUS_RESOLVED,
            'seed' => 1,
            'log' => ['turns' => []],
            'won' => true,
            'mark_earned' => true,
            'glory' => 10,
            'resolved_at' => now(),
        ]);

        $rite = $rites->openRite($season, $class);
        $fighters = $rites->eligibleFighters($class)->count();
        $base = BossArchetypeCatalog::raidBaseHp($fighters);
        $expected = BossArchetypeCatalog::weakenedRaidHp($base, 1);

        $this->assertSame(SeasonClassRite::STATUS_OPEN, $rite->status);
        $this->assertSame($base, $rite->boss_max_hp);
        $this->assertSame($expected, $rite->boss_hp);
        $this->assertSame(1, $rite->marks_applied);
        $this->assertLessThan($base, $rite->boss_hp);
    }

    public function test_resolve_rite_creates_wave_log_and_awards_relics(): void
    {
        [$teacher, $class, $student, $season] = $this->readyBossSeason(grade: 95, difficulty: 'easy');
        $rites = app(BossRiteService::class);
        $rites->openRite($season, $class);

        $this->actingAs($teacher)
            ->post(route('teacher.seasons.rite.resolve', [$season, $class]))
            ->assertRedirect();

        $rite = SeasonClassRite::query()->first();
        $this->assertTrue($rite->isResolved());
        $this->assertContains($rite->outcome, [
            SeasonClassRite::OUTCOME_BROKEN,
            SeasonClassRite::OUTCOME_RESISTED,
        ]);
        $this->assertNotEmpty($rite->log['waves']);
        $this->assertSame($student->id, $rite->log['waves'][0]['challenger_id']);
        $this->assertArrayHasKey('phase', $rite->log['waves'][0]['turns'][0]);

        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();

        $expectedRelics = $rite->wasBroken()
            ? BossArchetypeCatalog::RELICS_RITE_WIN
            : BossArchetypeCatalog::RELICS_RITE_LOSS;

        $this->assertSame($expectedRelics, (int) $enrollment->relics);
    }

    public function test_boss_combat_advances_phases_in_log(): void
    {
        $combat = app(ArenaCombatService::class);
        $boss = $combat->buildBossFighter('forge', 'normal', shadow: true);
        $this->assertSame('julgamento', $combat->bossPhaseForHp($boss['hp'], $boss['max_hp']));
        $this->assertSame('prova', $combat->bossPhaseForHp((int) floor($boss['max_hp'] * 0.5), $boss['max_hp']));
        $this->assertSame('veredito', $combat->bossPhaseForHp((int) floor($boss['max_hp'] * 0.2), $boss['max_hp']));

        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $student = $this->enrollFighter($class, 'Ana Souza', 'guerreiro');
        $this->gradeStudent($class, $student, 90);

        $result = $combat->resolveAgainstBoss($student, $class, $boss, 42_042, luckRange: 0);
        $phases = collect($result['turns'])->pluck('phase')->unique()->values()->all();
        $this->assertContains('julgamento', $phases);
        $this->assertSame($student->id, $result['fighters']['challenger']['id']);
        $this->assertTrue($result['fighters']['opponent']['is_boss'] ?? false);
    }

    public function test_arena_shows_boss_card_when_season_has_archetype(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason();
        app(BossRiteService::class)->setVigilOpen($season, true);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Rito da temporada')
            ->assertSee('Desafiar a Sombra')
            ->assertSee($season->bossDisplayName());
    }

    /**
     * @return array{0: User, 1: \App\Models\SchoolClass, 2: User, 3: Season}
     */
    private function readyBossSeason(float $grade = 80, string $difficulty = 'normal'): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $student = $this->enrollFighter($class, 'Ana Souza', 'guerreiro');
        $this->gradeStudent($class, $student, $grade);

        $season = Season::query()->create([
            'area_id' => $class->area_id,
            'name' => 'Temporada Boss',
            'description' => 'Teste',
            'boss_archetype' => 'eclipse',
            'boss_difficulty' => $difficulty,
            'vigil_open' => false,
            'created_by' => $teacher->id,
        ]);
        $season->classes()->sync([$class->id]);

        return [$teacher, $class, $student, $season];
    }

    private function enrollFighter(\App\Models\SchoolClass $class, string $name, string $characterClass = 'guerreiro'): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => $characterClass,
            'must_change_password' => false,
            'character_name' => 'Heroi '.$name,
            'character_avatar' => 'lobo',
            'character_approval_status' => 'approved',
        ]);

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'glory' => 0,
            'relics' => 0,
            'arena_wins' => 0,
            'arena_losses' => 0,
            'behavior_score' => 100,
        ]);

        return $student->fresh();
    }

    private function gradeStudent(\App\Models\SchoolClass $class, User $student, float $score): void
    {
        $activity = Activity::query()->firstOrCreate(
            ['class_id' => $class->id, 'name' => 'Prova Arena'],
            [
                'type' => 'individual',
                'max_score' => 100,
                'weight' => 1,
            ],
        );

        app(GameLoopService::class)->recordActivityGrade(
            $class,
            $activity,
            $score,
            $class->teacher,
            student: $student,
        );
    }
}
