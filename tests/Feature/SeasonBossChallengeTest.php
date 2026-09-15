<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AreaBalance;
use App\Models\BossVigil;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\SeasonClassBossBank;
use App\Models\SeasonClassRite;
use App\Models\User;
use App\Services\ArenaCombatService;
use App\Services\BossRiteService;
use App\Services\GameLoopService;
use App\Support\ArenaSchedule;
use App\Support\BossArchetypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonBossChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_save_boss_challenge_schedule_on_season(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $today = ArenaSchedule::todayWeekday();

        $days = [];
        foreach (ArenaSchedule::weekdays() as $weekday) {
            $days[$weekday] = ['daily_limit' => $weekday === $today ? 2 : 1];
        }

        $this->actingAs($teacher)
            ->post(route('teacher.seasons.store'), [
                'area_id' => $class->area_id,
                'name' => 'Temporada Chefão',
                'description' => null,
                'boss_archetype' => 'forge',
                'boss_difficulty' => 'normal',
                'class_ids' => [$class->id],
                'boss_challenge_days' => $days,
            ])
            ->assertRedirect(route('teacher.seasons.index'));

        $season = Season::query()->first();
        $this->assertNotNull($season);
        $this->assertSame(2, $season->bossChallengeDailyLimit());
        $this->assertSame(2, $season->bossChallengeWeek()[$today]);
    }

    public function test_boss_challenge_requires_fee_relics(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 95, difficulty: 'easy', relics: 5);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertSessionHasErrors('boss');

        $this->assertSame(0, BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->count());
        $this->assertSame(5, (int) $student->enrollmentIn($class)->fresh()->relics);
    }

    public function test_boss_challenge_debits_fee_even_on_loss(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 10, difficulty: 'elite', relics: 20);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $vigil = BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->first();
        $this->assertNotNull($vigil);
        $this->assertFalse((bool) $vigil->mark_earned);
        $this->assertSame(BossArchetypeCatalog::BOSS_CHALLENGE_FEE, (int) $vigil->fee_relics);
        $arenaName = (string) ($vigil->log['fighters']['opponent']['arena_name'] ?? '');
        $this->assertFalse(str_starts_with($arenaName, 'Sombra'));

        $this->assertFalse((bool) $vigil->won);

        $loot = $vigil->lootTotals();
        $pot = $loot['relics'] + $loot['seals'] + $loot['auras'];
        $this->assertGreaterThanOrEqual(1, $loot['relics']);
        $this->assertGreaterThanOrEqual(1, $loot['seals']);
        $this->assertGreaterThanOrEqual(1, $loot['auras']);
        $this->assertGreaterThanOrEqual(BossArchetypeCatalog::BOSS_CHALLENGE_LOSS_LOOT_FLOOR, $pot);
        $this->assertLessThanOrEqual(
            BossArchetypeCatalog::BOSS_CHALLENGE_LOSS_LOOT_FLOOR + BossArchetypeCatalog::BOSS_CHALLENGE_LOSS_LOOT_SPAN,
            $pot
        );

        $rolled = app(BossRiteService::class)->rollBossChallengeLoot($vigil->log, (int) $vigil->seed, false);
        $this->assertSame($loot, $rolled);

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(20 - BossArchetypeCatalog::BOSS_CHALLENGE_FEE + $loot['relics'], (int) $enrollment->relics);
        $this->assertSame($loot['seals'], (int) $enrollment->seals);
        $this->assertSame(1, (int) $enrollment->arena_losses);

        $auras = (int) AreaBalance::query()
            ->where('area_id', $season->area_id)
            ->where('student_id', $student->id)
            ->value('auras');
        $this->assertSame($loot['auras'], $auras);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->get(route('student.arena.vigil.show', $vigil))
            ->assertOk()
            ->assertSee('Consolação');
    }

    public function test_boss_challenge_daily_limit_blocks_second_attempt(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 80, difficulty: 'easy', relics: 50);

        BossVigil::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'source' => BossVigil::SOURCE_BOSS,
            'status' => BossVigil::STATUS_RESOLVED,
            'seed' => 1,
            'log' => ['turns' => [], 'winner_id' => $student->id],
            'won' => true,
            'mark_earned' => false,
            'glory' => 0,
            'fee_relics' => 10,
            'loot' => ['relics' => 1, 'seals' => 1, 'auras' => 1],
            'resolved_at' => now(),
        ]);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertSessionHasErrors('boss');
    }

    public function test_boss_challenge_weekday_limit_two_allows_second(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 80, difficulty: 'easy', relics: 50);
        $today = ArenaSchedule::todayWeekday();
        $schedule = BossArchetypeCatalog::bossChallengeScheduleFromValidated([
            $today => ['daily_limit' => 2],
        ]);
        // Fill other days with default so week is complete
        foreach (ArenaSchedule::weekdays() as $weekday) {
            $schedule[$weekday] = ['daily_limit' => $weekday === $today ? 2 : 1];
        }
        $season->update(['boss_challenge_schedule' => $schedule]);

        BossVigil::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'source' => BossVigil::SOURCE_BOSS,
            'status' => BossVigil::STATUS_RESOLVED,
            'seed' => 1,
            'log' => ['turns' => []],
            'won' => false,
            'mark_earned' => false,
            'glory' => 0,
            'fee_relics' => 10,
            'loot' => ['relics' => 0, 'seals' => 0, 'auras' => 0],
            'resolved_at' => now(),
        ]);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $this->assertSame(2, BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->count());
    }

    public function test_boss_challenge_does_not_earn_mark_or_consume_vigil_quota(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 95, difficulty: 'easy', relics: 30);
        app(BossRiteService::class)->setVigilOpen($season, true);

        BossVigil::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'source' => BossVigil::SOURCE_BOSS,
            'status' => BossVigil::STATUS_RESOLVED,
            'seed' => 1,
            'log' => ['turns' => []],
            'won' => true,
            'mark_earned' => false,
            'glory' => 0,
            'fee_relics' => 10,
            'loot' => ['relics' => 3, 'seals' => 2, 'auras' => 1],
            'resolved_at' => now(),
        ]);

        $this->assertSame(0, app(BossRiteService::class)->markCount($season, $class));
        $this->assertNull(app(BossRiteService::class)->vigilRestriction($season->fresh(), $class, $student));

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.vigil.challenge', $season))
            ->assertRedirect();

        $shadow = BossVigil::query()->where('source', BossVigil::SOURCE_STUDENT)->first();
        $this->assertNotNull($shadow);
    }

    public function test_boss_challenge_uses_full_boss_hp_not_shadow(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 90, difficulty: 'normal', relics: 20);
        $combat = app(ArenaCombatService::class);
        $full = $combat->buildBossFighter('eclipse', 'normal', shadow: false);
        $shadow = $combat->buildBossFighter('eclipse', 'normal', shadow: true);
        $this->assertGreaterThan($shadow['max_hp'], $full['max_hp']);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $vigil = BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->first();
        $this->assertSame($full['max_hp'], (int) $vigil->log['fighters']['opponent']['max_hp']);
        $this->assertSame($full['name'], $vigil->log['fighters']['opponent']['arena_name'] ?? $vigil->log['fighters']['opponent']['name']);
    }

    public function test_boss_challenge_win_awards_loot_matching_pot(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 99, difficulty: 'easy', relics: 25);
        $rites = app(BossRiteService::class);

        // Force a win path via service with easy boss + high grade; assert loot math when won.
        $vigil = null;
        for ($i = 0; $i < 8; $i++) {
            Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->update(['relics' => 50, 'seals' => 0, 'arena_wins' => 0, 'arena_losses' => 0]);
            BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->delete();
            SeasonClassBossBank::query()->where('season_id', $season->id)->delete();
            AreaBalance::query()->where('student_id', $student->id)->delete();

            $season->update([
                'boss_challenge_schedule' => BossArchetypeCatalog::bossChallengeScheduleFromValidated(
                    collect(ArenaSchedule::weekdays())->mapWithKeys(
                        fn ($d) => [$d => ['daily_limit' => 20]]
                    )->all()
                ),
            ]);

            $vigil = $rites->challengeBoss($season->fresh(), $class, $student);
            if ($vigil->won) {
                break;
            }
        }

        $this->assertNotNull($vigil);
        $this->assertTrue($vigil->won, 'Expected at least one win against easy boss with grade 99');

        $loot = $vigil->lootTotals();
        $jackpot = $vigil->jackpotTotals();
        $baseLoot = $loot;
        if ($jackpot !== null) {
            $baseLoot['relics'] = max(0, $baseLoot['relics'] - $jackpot['relics']);
        }
        $pot = $baseLoot['relics'] + $baseLoot['seals'] + $baseLoot['auras'];
        $this->assertGreaterThanOrEqual(BossArchetypeCatalog::BOSS_CHALLENGE_LOOT_FLOOR, $pot);
        $this->assertLessThanOrEqual(
            BossArchetypeCatalog::BOSS_CHALLENGE_LOOT_FLOOR + BossArchetypeCatalog::BOSS_CHALLENGE_LOOT_SPAN,
            $pot
        );

        $rolled = $rites->rollBossChallengeLoot($vigil->log, (int) $vigil->seed, true);
        $this->assertSame($baseLoot, $rolled);

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(50 - BossArchetypeCatalog::BOSS_CHALLENGE_FEE + $loot['relics'], (int) $enrollment->relics);
        $this->assertSame($loot['seals'], (int) $enrollment->seals);

        $auras = (int) AreaBalance::query()
            ->where('area_id', $season->area_id)
            ->where('student_id', $student->id)
            ->value('auras');
        $this->assertSame($loot['auras'], $auras);
    }

    public function test_boss_challenge_available_outside_and_after_rite(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 90, difficulty: 'easy', relics: 30);

        $this->assertNull(app(BossRiteService::class)->bossChallengeRestriction($season, $class, $student));

        SeasonClassRite::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'status' => SeasonClassRite::STATUS_RESOLVED,
            'boss_max_hp' => 280,
            'boss_hp' => 0,
            'marks_applied' => 0,
            'outcome' => SeasonClassRite::OUTCOME_BROKEN,
            'opened_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $this->assertSame(1, BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->count());
    }

    public function test_arena_shows_boss_challenge_button(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(relics: 15);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Desafiar o chefão')
            ->assertSee('ainda ganha um pouco')
            ->assertSee('Pote da turma')
            ->assertSee('Uma batalha sortuda leva uma fatia');
    }

    public function test_boss_challenge_deposits_fee_into_class_bank_without_jackpot_early(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 10, difficulty: 'elite', relics: 40);
        $this->unlimitedBossChallenges($season);

        SeasonClassBossBank::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'relics' => 20,
            'battles' => 2,
            'next_battle' => 13,
            'payout_percent' => 30,
        ]);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $bank = SeasonClassBossBank::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->first();
        $this->assertNotNull($bank);
        $this->assertSame(30, (int) $bank->relics);
        $this->assertSame(3, (int) $bank->battles);
        $this->assertSame(13, (int) $bank->next_battle);

        $vigil = BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->first();
        $this->assertNull($vigil->jackpotTotals());
    }

    public function test_boss_challenge_pays_jackpot_slice_and_resets_counter(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason(grade: 10, difficulty: 'elite', relics: 50);
        $this->unlimitedBossChallenges($season);

        SeasonClassBossBank::query()->create([
            'season_id' => $season->id,
            'class_id' => $class->id,
            'relics' => 100,
            'battles' => 12,
            'next_battle' => 13,
            'payout_percent' => 30,
        ]);

        $beforeRelics = (int) $student->enrollmentIn($class)->relics;

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $vigil = BossVigil::query()->where('source', BossVigil::SOURCE_BOSS)->first();
        $this->assertNotNull($vigil);
        $jackpot = $vigil->jackpotTotals();
        $this->assertNotNull($jackpot);
        $this->assertSame(110, $jackpot['bank_before']);
        $this->assertSame(30, $jackpot['percent']);
        $this->assertSame(33, $jackpot['relics']);

        $bank = SeasonClassBossBank::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->first();
        $this->assertSame(77, (int) $bank->relics);
        $this->assertSame(0, (int) $bank->battles);
        $this->assertGreaterThanOrEqual(BossArchetypeCatalog::JACKPOT_BATTLE_MIN, (int) $bank->next_battle);
        $this->assertLessThanOrEqual(BossArchetypeCatalog::JACKPOT_BATTLE_MAX, (int) $bank->next_battle);

        $loot = $vigil->lootTotals();
        $this->assertGreaterThanOrEqual(33, $loot['relics']);

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(
            $beforeRelics - BossArchetypeCatalog::BOSS_CHALLENGE_FEE + $loot['relics'],
            (int) $enrollment->relics
        );

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->get(route('student.arena.vigil.show', $vigil))
            ->assertOk()
            ->assertSee('Pote da turma')
            ->assertSee('30% de 110');
    }

    public function test_boss_bank_is_isolated_per_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['arena_open' => true, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['arena_open' => true, 'name' => 'Turma B', 'area_id' => $classA->area_id]);
        $studentA = $this->enrollFighter($classA, 'Ana A', 'guerreiro', 30);
        $this->gradeStudent($classA, $studentA, 10);

        $season = Season::query()->create([
            'area_id' => $classA->area_id,
            'name' => 'Temporada Isolada',
            'description' => null,
            'boss_archetype' => 'eclipse',
            'boss_difficulty' => 'elite',
            'vigil_open' => false,
            'created_by' => $teacher->id,
        ]);
        $season->classes()->sync([$classA->id, $classB->id]);
        $this->unlimitedBossChallenges($season);

        SeasonClassBossBank::query()->create([
            'season_id' => $season->id,
            'class_id' => $classA->id,
            'relics' => 0,
            'battles' => 0,
            'next_battle' => 20,
            'payout_percent' => 20,
        ]);
        SeasonClassBossBank::query()->create([
            'season_id' => $season->id,
            'class_id' => $classB->id,
            'relics' => 55,
            'battles' => 4,
            'next_battle' => 20,
            'payout_percent' => 20,
        ]);

        $this->actingAs($studentA)
            ->withSession(['current_class_id' => $classA->id])
            ->post(route('student.arena.boss.challenge', $season))
            ->assertRedirect();

        $bankA = SeasonClassBossBank::query()
            ->where('season_id', $season->id)
            ->where('class_id', $classA->id)
            ->first();
        $bankB = SeasonClassBossBank::query()
            ->where('season_id', $season->id)
            ->where('class_id', $classB->id)
            ->first();

        $this->assertSame(10, (int) $bankA->relics);
        $this->assertSame(1, (int) $bankA->battles);
        $this->assertSame(55, (int) $bankB->relics);
        $this->assertSame(4, (int) $bankB->battles);
    }

    /**
     * @return array{0: User, 1: SchoolClass, 2: User, 3: Season}
     */
    private function readyBossSeason(float $grade = 80, string $difficulty = 'normal', int $relics = 0): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $student = $this->enrollFighter($class, 'Ana Souza', 'guerreiro', $relics);
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

    private function unlimitedBossChallenges(Season $season): void
    {
        $season->update([
            'boss_challenge_schedule' => BossArchetypeCatalog::bossChallengeScheduleFromValidated(
                collect(ArenaSchedule::weekdays())->mapWithKeys(
                    fn ($d) => [$d => ['daily_limit' => 20]]
                )->all()
            ),
        ]);
    }

    private function enrollFighter(
        SchoolClass $class,
        string $name,
        string $characterClass = 'guerreiro',
        int $relics = 0,
    ): User {
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
            'relics' => $relics,
            'seals' => 0,
            'arena_wins' => 0,
            'arena_losses' => 0,
            'behavior_score' => 100,
        ]);

        return $student->fresh();
    }

    private function gradeStudent(SchoolClass $class, User $student, float $score): void
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
