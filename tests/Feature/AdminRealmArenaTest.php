<?php

namespace Tests\Feature;

use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminRealmArenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_realm_arena_and_cancel_pending_duel(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => true,
            'name' => 'Turma Norte',
        ]);
        $classB = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => true,
            'name' => 'Turma Sul',
        ]);
        $challenger = $this->enrollStudent($classA, 'Ana Souza');
        $opponent = $this->enrollStudent($classB, 'Bruno Lima', 'mago');

        $this->actingAs($challenger)
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $duel = RealmDuel::query()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.areas.arena', $area))
            ->assertOk()
            ->assertSee('Arena do reino')
            ->assertSee('Arena da turma')
            ->assertSee('Arena das turmas')
            ->assertSee('Turma Norte')
            ->assertSee('Turma Sul');

        $this->actingAs($admin)
            ->post(route('admin.areas.arena.cancel', [$area, $duel]))
            ->assertRedirect(route('admin.areas.arena', ['area' => $area, 'tab' => 'turmas']))
            ->assertSessionHas('success');

        $this->assertSame(RealmDuel::STATUS_DECLINED, $duel->fresh()->status);
        Notification::assertSentTo($challenger, GameAlert::class);
        Notification::assertSentTo($opponent, GameAlert::class);
    }

    public function test_teacher_cannot_access_admin_realm_arena(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);

        $this->actingAs($teacher)
            ->get(route('admin.areas.arena', $area))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_admin_can_update_realm_arena_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);

        $this->actingAs($admin)
            ->put(route('admin.areas.arena.update', $area), $this->realmSettingsPayload([
                'realm_arena_open' => 0,
                'realm_arena_cooldown_minutes' => 45,
                'realm_arena_daily_limit' => 2,
            ]))
            ->assertRedirect(route('admin.areas.arena', ['area' => $area, 'tab' => 'turmas']))
            ->assertSessionHas('success');

        $area->refresh();
        $this->assertFalse($area->isRealmArenaOpen());
        $this->assertSame(45, $area->realmArenaCooldownMinutes());
        $this->assertSame(2, $area->realmArenaDailyLimit());
        $this->assertTrue($area->hasRealmArenaSchedule());
        $this->assertFalse($area->realmArenaWeek()[1]['open']);
        $this->assertSame(45, $area->realmArenaWeek()[4]['cooldown_minutes']);
    }

    public function test_closed_realm_arena_blocks_challenge(): void
    {
        [$classA, $challenger, $classB, $opponent] = $this->readyRealmPair(
            challengerArenaOpen: true,
            opponentArenaOpen: true,
        );

        $classA->area->update(['realm_arena_open' => false]);

        $this->actingAs($challenger)
            ->from(route('student.arena.realm.index'))
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['arena']);
    }

    public function test_teacher_can_update_realm_arena_settings_from_class(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);
        $class = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => true,
            'name' => 'Turma Norte',
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.arena.realm.update', $class), $this->realmSettingsPayload([
                'realm_arena_open' => 1,
                'realm_arena_cooldown_minutes' => 15,
                'realm_arena_daily_limit' => 5,
            ]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena', 'arena_tab' => 'turmas']))
            ->assertSessionHas('success');

        $area->refresh();
        $this->assertTrue($area->isRealmArenaOpen());
        $this->assertSame(15, $area->realmArenaCooldownMinutes());
        $this->assertSame(5, $area->realmArenaDailyLimit());
        $this->assertTrue($area->hasRealmArenaSchedule());
        $this->assertSame(15, $area->realmArenaWeek()[6]['cooldown_minutes']);
        $this->assertSame(5, $area->realmArenaWeek()[7]['daily_limit']);
    }

    public function test_realm_challenges_follow_the_settings_of_the_current_weekday(): void
    {
        $this->travelTo('2026-09-14 15:00:00');

        [$classA, $challenger, $classB, $opponent] = $this->readyRealmPair(
            challengerArenaOpen: true,
            opponentArenaOpen: true,
        );

        $this->actingAs($classA->teacher)
            ->put(route('teacher.arena.realm.update', $classA), $this->realmSettingsPayload([
                'realm_days' => [
                    1 => ['open' => '0', 'cooldown_minutes' => 0, 'daily_limit' => 3],
                    2 => ['open' => '1', 'cooldown_minutes' => 0, 'daily_limit' => 3],
                ],
            ]))
            ->assertRedirect();

        $this->actingAs($challenger)
            ->from(route('student.arena.realm.index'))
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['arena']);

        $this->travelTo('2026-09-15 15:00:00');

        $this->actingAs($challenger)
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $this->assertSame(1, RealmDuel::query()->count());
    }

    public function test_teacher_can_cancel_pending_realm_duel_from_class_arena(): void
    {
        Notification::fake();

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => true,
            'name' => 'Turma Norte',
        ]);
        $classB = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => true,
            'name' => 'Turma Sul',
        ]);
        $challenger = $this->enrollStudent($classA, 'Ana Souza');
        $opponent = $this->enrollStudent($classB, 'Bruno Lima', 'mago');

        $this->actingAs($challenger)
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id]);

        $duel = RealmDuel::query()->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $classA, 'tab' => 'arena', 'arena_tab' => 'turmas']))
            ->assertOk()
            ->assertSee('Desafios entre turmas pendentes');

        $this->actingAs($teacher)
            ->post(route('teacher.arena.realm.cancel', [$classA, $duel]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $classA, 'tab' => 'arena', 'arena_tab' => 'turmas']))
            ->assertSessionHas('success');

        $this->assertSame(RealmDuel::STATUS_DECLINED, $duel->fresh()->status);
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: SchoolClass, 3: User}
     */
    private function readyRealmPair(bool $challengerArenaOpen, bool $opponentArenaOpen): array
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => $challengerArenaOpen,
            'name' => 'Turma Norte',
        ]);
        $classB = $this->createClassForTeacher($teacher, [
            'area' => $area,
            'arena_open' => $opponentArenaOpen,
            'name' => 'Turma Sul',
        ]);
        $challenger = $this->enrollStudent($classA, 'Ana Souza');
        $opponent = $this->enrollStudent($classB, 'Bruno Lima', 'mago');

        return [$classA, $challenger, $classB, $opponent];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function realmSettingsPayload(array $overrides = []): array
    {
        $open = $overrides['realm_arena_open'] ?? '1';
        $cooldown = $overrides['realm_arena_cooldown_minutes'] ?? 0;
        $limit = $overrides['realm_arena_daily_limit'] ?? RealmDuel::DAILY_RESOLVED_LIMIT;
        $perDay = $overrides['realm_days'] ?? [];

        $days = [];
        foreach (range(1, 7) as $weekday) {
            $days[$weekday] = array_merge([
                'open' => $open,
                'cooldown_minutes' => $cooldown,
                'daily_limit' => $limit,
            ], $perDay[$weekday] ?? []);
        }

        return ['realm_days' => $days];
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name,
        string $characterClass = 'guerreiro',
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

        $class->students()->syncWithoutDetaching([
            $student->id => [
                'ranking_visible' => true,
                'xp' => 0,
                'glory' => 0,
                'relics' => 0,
                'seals' => 0,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }
}
