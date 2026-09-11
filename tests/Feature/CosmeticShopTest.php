<?php

namespace Tests\Feature;

use App\Models\Duel;
use App\Models\Enrollment;
use App\Models\EnrollmentCosmetic;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\DuelService;
use App\Support\CosmeticCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CosmeticShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_shop_redirects_to_login(): void
    {
        $this->get(route('student.shop.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_duel_awards_relics_and_glory_but_hall_ranks_by_glory_only(): void
    {
        Notification::fake();

        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $challenger->enrollmentIn($class)->update(['relics' => 0, 'glory' => 0]);
        $opponent->enrollmentIn($class)->update(['relics' => 0, 'glory' => 0]);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->post(route('student.arena.accept', $duel));

        $duel->refresh();

        $winnerEnrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $duel->winner_id)
            ->firstOrFail();
        $loserId = $duel->winner_id === $challenger->id ? $opponent->id : $challenger->id;
        $loserEnrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $loserId)
            ->firstOrFail();

        $this->assertSame(Duel::GLORY_WIN, $winnerEnrollment->glory);
        $this->assertSame(Duel::GLORY_WIN, $winnerEnrollment->relics);
        $this->assertSame(Duel::GLORY_LOSS, $loserEnrollment->glory);
        $this->assertSame(Duel::GLORY_LOSS, $loserEnrollment->relics);

        $winnerEnrollment->update(['relics' => 999]);
        $loserEnrollment->update(['relics' => 0]);

        $hall = app(DuelService::class)->hall($class);
        $this->assertSame($duel->winner_id, $hall[0]['student']->id);
        $this->assertSame(Duel::GLORY_WIN, $hall[0]['glory']);
    }

    public function test_student_can_purchase_and_equip_cosmetic(): void
    {
        [$class, $student] = $this->readyStudent(relics: 100);

        $itemKey = 'acc_star';
        $price = CosmeticCatalog::item($itemKey)['price'];

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(100 - $price, $enrollment->relics);
        $this->assertTrue($enrollment->ownsCosmetic($itemKey));

        $this->actingAs($student)
            ->post(route('student.shop.equip'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'));

        $enrollment->refresh();
        $this->assertSame($itemKey, $enrollment->equipped_accessory);
    }

    public function test_purchase_rejects_insufficient_relics(): void
    {
        [$class, $student] = $this->readyStudent(relics: 5);

        $this->actingAs($student)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => 'frame_gold'])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertSame(5, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, EnrollmentCosmetic::query()->count());
    }

    public function test_purchase_rejects_duplicate_item(): void
    {
        [$class, $student] = $this->readyStudent(relics: 200);
        $itemKey = 'title_duelist';
        $enrollment = $student->enrollmentIn($class);

        EnrollmentCosmetic::query()->create([
            'enrollment_id' => $enrollment->id,
            'item_key' => $itemKey,
        ]);

        $before = (int) $enrollment->fresh()->relics;

        $this->actingAs($student)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertSame($before, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(1, EnrollmentCosmetic::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_cannot_equip_unowned_item(): void
    {
        [$class, $student] = $this->readyStudent(relics: 50);

        $this->actingAs($student)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.equip'), ['item' => 'aura_ember'])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertNull($student->enrollmentIn($class)->fresh()->equipped_aura);
    }

    public function test_unequip_clears_slot(): void
    {
        [$class, $student] = $this->readyStudent(relics: 50);
        $enrollment = $student->enrollmentIn($class);

        EnrollmentCosmetic::query()->create([
            'enrollment_id' => $enrollment->id,
            'item_key' => 'title_champion',
        ]);
        $enrollment->update(['equipped_title' => 'title_champion']);

        $this->actingAs($student)
            ->post(route('student.shop.unequip'), ['slot' => 'title'])
            ->assertRedirect(route('student.shop.index'));

        $this->assertNull($enrollment->fresh()->equipped_title);
    }

    public function test_cosmetics_are_scoped_per_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $classA->area, 'name' => 'Turma B']);
        $student = $this->enrollStudent($classA, 'Ana', relics: 100);
        $this->enrollExisting($classB, $student, relics: 0);

        $itemKey = 'acc_crown';

        $this->actingAs($student)
            ->withSession(['current_class_id' => $classA->id])
            ->post(route('student.shop.purchase'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'));

        $this->actingAs($student)
            ->withSession(['current_class_id' => $classA->id])
            ->post(route('student.shop.equip'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'));

        $enrollmentA = $student->enrollmentIn($classA)->fresh();
        $enrollmentB = $student->enrollmentIn($classB)->fresh();

        $this->assertTrue($enrollmentA->ownsCosmetic($itemKey));
        $this->assertSame($itemKey, $enrollmentA->equipped_accessory);
        $this->assertFalse($enrollmentB->ownsCosmetic($itemKey));
        $this->assertNull($enrollmentB->equipped_accessory);
        $this->assertSame(0, (int) $enrollmentB->relics);
    }

    public function test_student_can_list_owned_item_and_classmate_can_buy(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $seller = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $buyer = $this->enrollStudent($class, 'Bruno Lima', relics: 80);

        $this->actingAs($seller)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($seller)
            ->post(route('student.shop.equip'), ['item' => 'acc_star']);

        $this->actingAs($seller)
            ->post(route('student.shop.list'), ['item' => 'acc_star', 'price' => 40])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $listing = $seller->enrollmentIn($class)->listings()->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('student.shop.listings.buy', $listing))
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $sellerEnrollment = $seller->enrollmentIn($class)->fresh();
        $buyerEnrollment = $buyer->enrollmentIn($class)->fresh();
        $price = CosmeticCatalog::item('acc_star')['price'];

        $this->assertFalse($sellerEnrollment->ownsCosmetic('acc_star'));
        $this->assertNull($sellerEnrollment->equipped_accessory);
        $this->assertSame(100 - $price + 40, (int) $sellerEnrollment->relics);
        $this->assertTrue($buyerEnrollment->ownsCosmetic('acc_star'));
        $this->assertSame(80 - 40, (int) $buyerEnrollment->relics);
        $this->assertSame(0, $sellerEnrollment->listings()->count());
    }

    public function test_cannot_buy_own_listing(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $seller = $this->enrollStudent($class, 'Ana Souza', relics: 100);

        $this->actingAs($seller)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($seller)
            ->post(route('student.shop.list'), ['item' => 'acc_star', 'price' => 40]);

        $listing = $seller->enrollmentIn($class)->listings()->firstOrFail();

        $this->actingAs($seller)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.listings.buy', $listing))
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('listing');

        $this->assertTrue($seller->enrollmentIn($class)->ownsCosmetic('acc_star'));
    }

    public function test_cannot_buy_listing_from_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $classA->area, 'name' => 'Turma B']);
        $seller = $this->enrollStudent($classA, 'Ana Souza', relics: 100);
        $buyer = $this->enrollStudent($classB, 'Bruno Lima', relics: 80);

        $this->actingAs($seller)
            ->withSession(['current_class_id' => $classA->id])
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($seller)
            ->withSession(['current_class_id' => $classA->id])
            ->post(route('student.shop.list'), ['item' => 'acc_star', 'price' => 40]);

        $listing = $seller->enrollmentIn($classA)->listings()->firstOrFail();

        $this->actingAs($buyer)
            ->withSession(['current_class_id' => $classB->id])
            ->post(route('student.shop.listings.buy', $listing))
            ->assertNotFound();

        $this->assertTrue($seller->enrollmentIn($classA)->ownsCosmetic('acc_star'));
        $this->assertFalse($buyer->enrollmentIn($classB)->ownsCosmetic('acc_star'));
        $this->assertSame(80, (int) $buyer->enrollmentIn($classB)->fresh()->relics);
    }

    public function test_shop_page_shows_relic_balance(): void
    {
        [$class, $student] = $this->readyStudent(relics: 42);

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Loja de cosméticos')
            ->assertSee('42 Relíquias')
            ->assertSee('Anel de Bronze')
            ->assertSee('Mercado da turma')
            ->assertSee('Assíduo')
            ->assertSee('Selos')
            ->assertSee('🟤')
            ->assertSee('📅');
    }

    public function test_student_sees_shop_price_only_while_item_is_for_sale_in_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $owner = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $classmate = $this->enrollStudent($class, 'Bruno Lima', relics: 80);
        $shopPrice = CosmeticCatalog::item('acc_star')['price'].' Relíquias';

        $this->actingAs($owner)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee($shopPrice);

        $this->actingAs($owner)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star'])
            ->assertRedirect(route('student.shop.index'));

        $this->actingAs($owner)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Estrela Guardiã')
            ->assertSee('Anunciar')
            ->assertDontSee($shopPrice)
            ->assertDontSee('value="'.CosmeticCatalog::item('acc_star')['price'].'"', false);

        $this->actingAs($classmate)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Estrela Guardiã')
            ->assertSee('Esgotado na loja')
            ->assertDontSee($shopPrice);
    }

    public function test_classmate_still_sees_shop_price_when_copies_remain(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $owner = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $classmate = $this->enrollStudent($class, 'Bruno Lima', relics: 80);
        $shopPrice = CosmeticCatalog::item('acc_star')['price'].' Relíquias';

        $this->actingAs($teacher)
            ->post(route('teacher.shop.restock', $class), [
                'item' => 'acc_star',
                'quantity' => 2,
            ]);

        $this->actingAs($owner)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($owner)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertDontSee($shopPrice);

        $this->actingAs($classmate)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee($shopPrice);
    }

    public function test_teacher_and_admin_still_see_shop_price_after_purchase(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $shopPrice = CosmeticCatalog::item('acc_star')['price'].' Relíquias';

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('Estrela Guardiã')
            ->assertSee($shopPrice);

        $this->actingAs($admin)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('Estrela Guardiã')
            ->assertSee($shopPrice);
    }

    public function test_student_can_purchase_seal_item_with_seals(): void
    {
        [$class, $student] = $this->readyStudent(relics: 100, seals: 20);
        $itemKey = 'title_assiduous';
        $price = CosmeticCatalog::item($itemKey)['price'];

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => $itemKey])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(20 - $price, (int) $enrollment->seals);
        $this->assertSame(100, (int) $enrollment->relics);
        $this->assertTrue($enrollment->ownsCosmetic($itemKey));
    }

    public function test_seal_item_rejects_purchase_without_seals_even_with_relics(): void
    {
        [$class, $student] = $this->readyStudent(relics: 999, seals: 0);

        $this->actingAs($student)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => 'frame_aurora'])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertFalse($student->enrollmentIn($class)->ownsCosmetic('frame_aurora'));
        $this->assertSame(999, (int) $student->enrollmentIn($class)->fresh()->relics);
    }

    public function test_cannot_list_seal_item_on_peer_market(): void
    {
        [$class, $student] = $this->readyStudent(relics: 0, seals: 50);
        $itemKey = 'acc_seal';

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
    private function readyPair(bool $arenaOpen = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => $arenaOpen]);
        $challenger = $this->enrollStudent($class, 'Ana Souza');
        $opponent = $this->enrollStudent($class, 'Bruno Lima', characterClass: 'mago');

        return [$class, $challenger, $opponent];
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function readyStudent(int $relics = 0, int $seals = 0): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: $relics, seals: $seals);

        return [$class, $student];
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name,
        string $characterClass = 'guerreiro',
        int $relics = 0,
        int $seals = 0,
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

        return $this->enrollExisting($class, $student, $relics, $seals);
    }

    private function enrollExisting(SchoolClass $class, User $student, int $relics = 0, int $seals = 0): User
    {
        $class->students()->syncWithoutDetaching([
            $student->id => [
                'ranking_visible' => true,
                'xp' => 0,
                'glory' => 0,
                'relics' => $relics,
                'seals' => $seals,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }
}
