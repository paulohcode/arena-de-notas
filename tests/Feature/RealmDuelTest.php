<?php

namespace Tests\Feature;

use App\Models\AreaBalance;
use App\Models\AreaCosmeticStock;
use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\ShopItem;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\CosmeticCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RealmDuelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_challenge_same_class_classmate(): void
    {
        [$classA, $challenger, $classmate] = $this->readySameClassPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->from(route('student.arena.realm.index'))
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $classmate->id])
            ->assertRedirect(route('student.arena.realm.index'))
            ->assertSessionHasErrors(['opponent_id']);
    }

    public function test_cannot_challenge_student_from_another_area(): void
    {
        [$classA, $challenger, $classB, $opponent] = $this->readyCrossAreaPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->from(route('student.arena.realm.index'))
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect(route('student.arena.realm.index'))
            ->assertSessionHasErrors(['opponent_id']);
    }

    public function test_closed_arena_on_either_side_blocks_challenge(): void
    {
        [$classA, $challenger, $classB, $opponent] = $this->readyRealmPair(
            challengerArenaOpen: true,
            opponentArenaOpen: false,
        );

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['arena']);
    }

    public function test_full_realm_duel_awards_aura_without_glory_or_relics(): void
    {
        Notification::fake();

        [$classA, $challenger, $classB, $opponent] = $this->readyRealmPair(
            challengerArenaOpen: true,
            opponentArenaOpen: true,
        );

        $challengerEnrollment = $challenger->enrollmentIn($classA);
        $challengerEnrollment->update(['xp' => 200, 'glory' => 5, 'relics' => 7, 'arena_wins' => 1, 'arena_losses' => 2]);
        $opponentEnrollment = $opponent->enrollmentIn($classB);
        $opponentEnrollment->update(['xp' => 150, 'glory' => 3, 'relics' => 4, 'arena_wins' => 0, 'arena_losses' => 1]);

        $this->actingAs($challenger)
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect(route('student.arena.realm.show', RealmDuel::query()->firstOrFail()))
            ->assertSessionHas('success');

        $duel = RealmDuel::query()->firstOrFail();
        $this->assertSame(RealmDuel::STATUS_PENDING, $duel->status);
        $this->assertSame($classA->area_id, $duel->area_id);

        Notification::assertSentTo($opponent, GameAlert::class);

        $this->actingAs($opponent)
            ->post(route('student.arena.realm.accept', $duel))
            ->assertRedirect();

        $duel->refresh();
        $this->assertSame(RealmDuel::STATUS_RESOLVED, $duel->status);
        $this->assertNotNull($duel->winner_id);
        $this->assertSame(RealmDuel::AURA_WIN, (int) $duel->aura_winner);
        $this->assertSame(RealmDuel::AURA_LOSS, (int) $duel->aura_loser);

        $winnerId = (int) $duel->winner_id;
        $loserId = $winnerId === $challenger->id ? $opponent->id : $challenger->id;

        $this->assertSame(
            RealmDuel::AURA_WIN,
            (int) AreaBalance::query()->where('area_id', $classA->area_id)->where('student_id', $winnerId)->value('auras'),
        );
        $this->assertSame(
            RealmDuel::AURA_LOSS,
            (int) AreaBalance::query()->where('area_id', $classA->area_id)->where('student_id', $loserId)->value('auras'),
        );

        $challengerEnrollment->refresh();
        $opponentEnrollment->refresh();

        $this->assertSame(5, (int) $challengerEnrollment->glory);
        $this->assertSame(7, (int) $challengerEnrollment->relics);
        $this->assertSame(1, (int) $challengerEnrollment->arena_wins);
        $this->assertSame(2, (int) $challengerEnrollment->arena_losses);
        $this->assertSame(200, (int) $challengerEnrollment->xp);

        $this->assertSame(3, (int) $opponentEnrollment->glory);
        $this->assertSame(4, (int) $opponentEnrollment->relics);
        $this->assertSame(0, (int) $opponentEnrollment->arena_wins);
        $this->assertSame(1, (int) $opponentEnrollment->arena_losses);
        $this->assertSame(150, (int) $opponentEnrollment->xp);
    }

    public function test_pending_json_includes_realm_challenges(): void
    {
        Notification::fake();

        [$classA, $challenger, $classB, $opponent] = $this->readyRealmPair(
            challengerArenaOpen: true,
            opponentArenaOpen: true,
        );

        $this->actingAs($challenger)
            ->post(route('student.arena.realm.challenge'), ['opponent_id' => $opponent->id]);

        $duel = RealmDuel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->getJson(route('student.arena.pending'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'realm-'.$duel->id,
                'kind' => 'realm',
                'realm_duel_id' => $duel->id,
            ]);
    }

    public function test_student_can_open_realm_arena_shortcut(): void
    {
        [$classA, $challenger, $classB, $opponent] = $this->readyRealmPair(
            challengerArenaOpen: true,
            opponentArenaOpen: true,
        );

        $this->actingAs($challenger)
            ->get(route('student.arena.realm.index'))
            ->assertOk()
            ->assertSee('Arena entre turmas')
            ->assertSee($opponent->name);
    }

    public function test_purchase_with_aura_debits_area_balance(): void
    {
        [$class, $student] = $this->readyShopStudent();
        $itemKey = 'aura_test_badge';
        $this->createAuraItem($class, $itemKey, price: 15);

        AreaBalance::query()->create([
            'area_id' => $class->area_id,
            'student_id' => $student->id,
            'auras' => 40,
        ]);

        $enrollment = $student->enrollmentIn($class);
        $enrollment->update(['relics' => 100, 'seals' => 50]);

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $this->assertSame(25, (int) AreaBalance::query()
            ->where('area_id', $class->area_id)
            ->where('student_id', $student->id)
            ->value('auras'));
        $this->assertSame(100, (int) $enrollment->fresh()->relics);
        $this->assertSame(50, (int) $enrollment->fresh()->seals);
        $this->assertTrue($enrollment->fresh()->ownsCosmetic($itemKey));
    }

    public function test_relic_item_does_not_accept_aura_balance(): void
    {
        [$class, $student] = $this->readyShopStudent();

        AreaBalance::query()->create([
            'area_id' => $class->area_id,
            'student_id' => $student->id,
            'auras' => 999,
        ]);

        $enrollment = $student->enrollmentIn($class);
        $enrollment->update(['relics' => 0]);

        $this->actingAs($student)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => 'acc_star'])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');
    }

    public function test_cannot_list_aura_item_on_peer_market(): void
    {
        [$class, $student] = $this->readyShopStudent();
        $itemKey = 'aura_test_frame';
        $this->createAuraItem($class, $itemKey, price: 10);

        AreaBalance::query()->create([
            'area_id' => $class->area_id,
            'student_id' => $student->id,
            'auras' => 20,
        ]);

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => $itemKey])
            ->assertSessionHas('success');

        $this->actingAs($student)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.list'), ['item' => $itemKey, 'price' => 5])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertSame(0, $student->enrollmentIn($class)->listings()->count());
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: User}
     */
    private function readySameClassPair(bool $arenaOpen = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => $arenaOpen, 'name' => 'Turma A']);
        $challenger = $this->enrollStudent($class, 'Ana Souza');
        $classmate = $this->enrollStudent($class, 'Bruno Lima', characterClass: 'mago');

        return [$class, $challenger, $classmate];
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: SchoolClass, 3: User}
     */
    private function readyCrossAreaPair(bool $arenaOpen = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['arena_open' => $arenaOpen, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['arena_open' => $arenaOpen, 'name' => 'Turma B']);
        $challenger = $this->enrollStudent($classA, 'Ana Souza');
        $opponent = $this->enrollStudent($classB, 'Carla Dias', characterClass: 'mago');

        return [$classA, $challenger, $classB, $opponent];
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: SchoolClass, 3: User}
     */
    private function readyRealmPair(bool $challengerArenaOpen, bool $opponentArenaOpen): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
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
        $opponent = $this->enrollStudent($classB, 'Bruno Lima', characterClass: 'mago');

        return [$classA, $challenger, $classB, $opponent];
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function readyShopStudent(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        return [$class, $student];
    }

    private function createAuraItem(SchoolClass $class, string $itemKey, int $price): void
    {
        ShopItem::query()->create([
            'class_id' => null,
            'area_id' => $class->area_id,
            'item_key' => $itemKey,
            'slot' => CosmeticCatalog::SLOT_ACCESSORY,
            'name' => 'Item Aura Teste',
            'price' => $price,
            'currency' => CosmeticCatalog::CURRENCY_AURAS,
            'rarity' => 'rare',
            'icon' => '✨',
            'css' => null,
            'label' => null,
        ]);

        AreaCosmeticStock::query()->updateOrCreate(
            [
                'area_id' => $class->area_id,
                'item_key' => $itemKey,
            ],
            ['quantity' => 5],
        );

        CosmeticCatalog::flush();
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
