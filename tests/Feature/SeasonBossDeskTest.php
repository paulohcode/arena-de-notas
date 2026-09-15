<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\BossVigil;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\User;
use App\Services\BossRiteService;
use App\Services\GameLoopService;
use App\Support\BossArchetypeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonBossDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_open_boss_desk_and_challenge_student(): void
    {
        [$teacher, $class, $student, $season] = $this->readyBossSeason();

        $this->actingAs($teacher)
            ->get(route('teacher.seasons.boss', $season))
            ->assertOk()
            ->assertSee('Mesa do Chefão')
            ->assertSee('Desafiar')
            ->assertSee($student->name);

        $this->actingAs($teacher)
            ->post(route('teacher.seasons.boss.challenge', $season), [
                'class_id' => $class->id,
                'student_id' => $student->id,
            ])
            ->assertRedirect();

        $vigil = BossVigil::query()->first();
        $this->assertNotNull($vigil);
        $this->assertSame(BossVigil::SOURCE_STAFF, $vigil->source);
        $this->assertSame($teacher->id, $vigil->initiated_by);
        $this->assertFalse($vigil->mark_earned);
        $this->assertSame(0, app(BossRiteService::class)->markCount($season, $class));

        $this->actingAs($teacher)
            ->get(route('teacher.seasons.vigil.show', [$season, $vigil]))
            ->assertOk()
            ->assertSee('Mesa do chefão')
            ->assertSee($season->bossDisplayName());

        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertGreaterThan(0, (int) $enrollment->glory);
    }

    public function test_admin_can_use_boss_desk_on_any_season(): void
    {
        [, $class, $student, $season] = $this->readyBossSeason();
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('teacher.seasons.boss', $season))
            ->assertOk()
            ->assertSee('Desafiar');

        $this->actingAs($admin)
            ->post(route('teacher.seasons.boss.challenge', $season), [
                'class_id' => $class->id,
                'student_id' => $student->id,
            ])
            ->assertRedirect();

        $this->assertSame(BossVigil::SOURCE_STAFF, BossVigil::query()->first()->source);
    }

    public function test_staff_challenge_does_not_consume_student_vigil_limit(): void
    {
        [$teacher, $class, $student, $season] = $this->readyBossSeason();
        $rites = app(BossRiteService::class);
        $rites->setVigilOpen($season, true);

        $rites->staffChallenge($season, $class, $student, $teacher);

        $this->assertSame(0, $rites->resolvedVigilsToday($season, $class, $student));
        $this->assertNull($rites->vigilRestriction($season, $class, $student));
    }

    public function test_staff_cannot_challenge_student_without_persona(): void
    {
        [$teacher, $class, , $season] = $this->readyBossSeason();
        $incomplete = User::factory()->create([
            'role' => 'student',
            'character_class' => 'mago',
            'must_change_password' => false,
            'character_approval_status' => 'pending',
        ]);
        $class->students()->attach($incomplete->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'glory' => 0,
            'relics' => 0,
            'arena_wins' => 0,
            'arena_losses' => 0,
            'behavior_score' => 100,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.seasons.boss.challenge', $season), [
                'class_id' => $class->id,
                'student_id' => $incomplete->id,
            ])
            ->assertSessionHasErrors('challenge');
    }

    /**
     * @return array{0: User, 1: SchoolClass, 2: User, 3: Season}
     */
    private function readyBossSeason(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $student = $this->enrollFighter($class, 'Ana Souza', 'guerreiro');
        $this->gradeStudent($class, $student, 85);

        $season = Season::query()->create([
            'area_id' => $class->area_id,
            'name' => 'Temporada Desk',
            'description' => 'Teste mesa',
            'boss_archetype' => 'forge',
            'boss_difficulty' => BossArchetypeCatalog::DIFFICULTY_NORMAL,
            'vigil_open' => false,
            'created_by' => $teacher->id,
        ]);
        $season->classes()->sync([$class->id]);

        return [$teacher, $class, $student, $season];
    }

    private function enrollFighter(SchoolClass $class, string $name, string $characterClass = 'guerreiro'): User
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
