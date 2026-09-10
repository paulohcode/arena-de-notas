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
            ->assertSee('Turma Norte')
            ->assertSee('Turma Sul');

        $this->actingAs($admin)
            ->post(route('admin.areas.arena.cancel', [$area, $duel]))
            ->assertRedirect(route('admin.areas.arena', $area))
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
            ->put(route('admin.areas.arena.update', $area), [
                'realm_arena_open' => 0,
                'realm_arena_cooldown_minutes' => 45,
                'realm_arena_daily_limit' => 2,
            ])
            ->assertRedirect(route('admin.areas.arena', $area))
            ->assertSessionHas('success');

        $area->refresh();
        $this->assertFalse($area->isRealmArenaOpen());
        $this->assertSame(45, $area->realmArenaCooldownMinutes());
        $this->assertSame(2, $area->realmArenaDailyLimit());
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
            ->put(route('teacher.arena.realm.update', $class), [
                'realm_arena_open' => 1,
                'realm_arena_cooldown_minutes' => 15,
                'realm_arena_daily_limit' => 5,
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']))
            ->assertSessionHas('success');

        $area->refresh();
        $this->assertTrue($area->isRealmArenaOpen());
        $this->assertSame(15, $area->realmArenaCooldownMinutes());
        $this->assertSame(5, $area->realmArenaDailyLimit());
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
            ->get(route('teacher.classes.show', ['schoolClass' => $classA, 'tab' => 'arena']))
            ->assertOk()
            ->assertSee('Desafios entre turmas pendentes');

        $this->actingAs($teacher)
            ->post(route('teacher.arena.realm.cancel', [$classA, $duel]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $classA, 'tab' => 'arena']))
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
