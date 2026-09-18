<?php

namespace Tests\Feature;

use App\Models\AreaBalance;
use App\Models\CurrencyTrade;
use App\Models\CurrencyTradeListing;
use App\Models\ExchangeRaffle;
use App\Models\ExchangeVault;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeerCurrencyTradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_listing_debits_offer_and_cancel_returns_it(): void
    {
        [$class, $seller] = $this->studentInClass(relics: 0, seals: 50);

        $this->actingAs($seller)
            ->post(route('student.exchange.listings.store'), [
                'offer_currency' => 'seals',
                'offer_amount' => 50,
                'ask_currency' => 'relics',
                'ask_amount' => 100,
            ])
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']))
            ->assertSessionHas('success');

        $this->assertSame(0, (int) $seller->enrollmentIn($class)->fresh()->seals);
        $listing = CurrencyTradeListing::query()->firstOrFail();
        $this->assertSame(CurrencyTradeListing::STATUS_OPEN, $listing->status);

        $this->actingAs($seller)
            ->post(route('student.exchange.listings.cancel', $listing))
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']));

        $this->assertSame(50, (int) $seller->enrollmentIn($class)->fresh()->seals);
        $this->assertSame(CurrencyTradeListing::STATUS_CANCELLED, $listing->fresh()->status);
    }

    public function test_accept_same_class_moves_balances_and_fees_vault(): void
    {
        [$class, $seller] = $this->studentInClass(name: 'Vendedor', relics: 0, seals: 50);
        $buyer = $this->enrollStudent($class, 'Comprador', relics: 200, seals: 0);

        $listing = $this->createOpenListing($seller, $class, 'seals', 50, 'relics', 100);

        $this->actingAs($buyer)
            ->post(route('student.exchange.listings.accept', $listing))
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']))
            ->assertSessionHas('success');

        $this->assertSame(100, (int) $seller->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, (int) $seller->enrollmentIn($class)->fresh()->seals);
        $this->assertSame(90, (int) $buyer->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(50, (int) $buyer->enrollmentIn($class)->fresh()->seals);

        $vault = ExchangeVault::query()->where('area_id', $class->area_id)->firstOrFail();
        $this->assertSame(10, (int) $vault->relics);

        $trade = CurrencyTrade::query()->firstOrFail();
        $this->assertSame(10, (int) $trade->fee_amount);
        $this->assertSame('relics', $trade->fee_currency);
        $this->assertSame(CurrencyTradeListing::STATUS_SOLD, $listing->fresh()->status);
    }

    public function test_accept_across_classes_same_realm(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma B']);

        $seller = $this->enrollStudent($classA, 'Vendedor', relics: 0, seals: 50);
        $buyer = $this->enrollStudent($classB, 'Comprador', relics: 200, seals: 0);
        $listing = $this->createOpenListing($seller, $classA, 'seals', 50, 'relics', 100);

        $this->actingAs($buyer)
            ->withSession(['current_class_id' => $classB->id])
            ->post(route('student.exchange.listings.accept', $listing))
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']));

        $this->assertSame(100, (int) $seller->enrollmentIn($classA)->fresh()->relics);
        $this->assertSame(50, (int) $buyer->enrollmentIn($classB)->fresh()->seals);
        $this->assertSame(90, (int) $buyer->enrollmentIn($classB)->fresh()->relics);
    }

    public function test_accept_from_different_realm_is_blocked(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['name' => 'Turma B']);

        $seller = $this->enrollStudent($classA, 'Vendedor', seals: 50);
        $buyer = $this->enrollStudent($classB, 'Comprador', relics: 200);
        $listing = $this->createOpenListing($seller, $classA, 'seals', 50, 'relics', 100);

        $this->actingAs($buyer)
            ->withSession(['current_class_id' => $classB->id])
            ->from(route('student.exchange.index', ['tab' => 'negociar']))
            ->post(route('student.exchange.listings.accept', $listing))
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']))
            ->assertSessionHasErrors('listing');

        $this->assertSame(CurrencyTradeListing::STATUS_OPEN, $listing->fresh()->status);
        $this->assertSame(0, CurrencyTrade::query()->count());
    }

    public function test_buyer_without_balance_for_price_plus_fee_is_rejected(): void
    {
        [$class, $seller] = $this->studentInClass(name: 'Vendedor', seals: 50);
        $buyer = $this->enrollStudent($class, 'Comprador', relics: 105);
        $listing = $this->createOpenListing($seller, $class, 'seals', 50, 'relics', 100);

        $this->actingAs($buyer)
            ->from(route('student.exchange.index', ['tab' => 'negociar']))
            ->post(route('student.exchange.listings.accept', $listing))
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']))
            ->assertSessionHasErrors('listing');

        $this->assertSame(105, (int) $buyer->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, CurrencyTrade::query()->count());
    }

    public function test_seller_cannot_accept_own_listing(): void
    {
        [$class, $seller] = $this->studentInClass(relics: 200, seals: 50);
        $listing = $this->createOpenListing($seller, $class, 'seals', 50, 'relics', 100);

        $this->actingAs($seller)
            ->from(route('student.exchange.index', ['tab' => 'negociar']))
            ->post(route('student.exchange.listings.accept', $listing))
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']))
            ->assertSessionHasErrors('listing');
    }

    public function test_class_without_realm_cannot_create_listing(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['area_id' => null]);
        $seller = $this->enrollStudent($class, 'Vendedor', seals: 50);

        $this->actingAs($seller)
            ->from(route('student.exchange.index', ['tab' => 'negociar']))
            ->post(route('student.exchange.listings.store'), [
                'offer_currency' => 'seals',
                'offer_amount' => 50,
                'ask_currency' => 'relics',
                'ask_amount' => 100,
            ])
            ->assertRedirect(route('student.exchange.index', ['tab' => 'negociar']))
            ->assertSessionHasErrors('listing');
    }

    public function test_admin_raffle_pays_winner_and_clears_vault(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        [$class, $seller] = $this->studentInClass(name: 'Vendedor', seals: 50);
        $buyer = $this->enrollStudent($class, 'Comprador', relics: 200);
        $listing = $this->createOpenListing($seller, $class, 'seals', 50, 'relics', 100);

        $this->actingAs($buyer)->post(route('student.exchange.listings.accept', $listing));

        $vault = ExchangeVault::query()->where('area_id', $class->area_id)->firstOrFail();
        $this->assertSame(10, (int) $vault->relics);

        $this->actingAs($admin)
            ->post(route('admin.exchange.raffle', $class->area))
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $raffle = ExchangeRaffle::query()->firstOrFail();
        $this->assertSame(10, (int) $raffle->relics);
        $this->assertContains($raffle->winner_id, [$seller->id, $buyer->id]);

        $vault->refresh();
        $this->assertTrue($vault->isEmpty());

        $winner = User::query()->findOrFail($raffle->winner_id);
        $winnerRelics = (int) $winner->enrollmentIn($class)->fresh()->relics;
        if ($winner->is($seller)) {
            $this->assertSame(110, $winnerRelics);
        } else {
            $this->assertSame(100, $winnerRelics);
        }
    }

    public function test_admin_raffle_rejects_empty_vault(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        ExchangeVault::forArea($class->area);

        $this->actingAs($admin)
            ->from(route('admin.exchange.index'))
            ->post(route('admin.exchange.raffle', $class->area))
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHasErrors('raffle');
    }

    public function test_admin_exchange_index_shows_vaults(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $vault = ExchangeVault::forArea($class->area);
        $vault->update(['relics' => 25]);

        $this->actingAs($admin)
            ->get(route('admin.exchange.index'))
            ->assertOk()
            ->assertSee('Cofres por reino')
            ->assertSee($class->area->name)
            ->assertSee('25 Relíquias')
            ->assertSee('Sortear pote');
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function studentInClass(
        string $name = 'Aluno',
        int $relics = 0,
        int $seals = 0,
        int $auras = 0,
    ): array {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, $name, relics: $relics, seals: $seals);
        $this->setAuras($student, $class, $auras);

        return [$class, $student];
    }

    private function createOpenListing(
        User $seller,
        SchoolClass $class,
        string $offerCurrency,
        int $offerAmount,
        string $askCurrency,
        int $askAmount,
    ): CurrencyTradeListing {
        $this->actingAs($seller)
            ->post(route('student.exchange.listings.store'), [
                'offer_currency' => $offerCurrency,
                'offer_amount' => $offerAmount,
                'ask_currency' => $askCurrency,
                'ask_amount' => $askAmount,
            ])
            ->assertSessionHas('success');

        return CurrencyTradeListing::query()->latest('id')->firstOrFail();
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name,
        int $relics = 0,
        int $seals = 0,
    ): User {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => 'guerreiro',
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
                'relics' => $relics,
                'seals' => $seals,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }

    private function setAuras(User $student, SchoolClass $class, int $auras): void
    {
        if (! $class->area_id) {
            return;
        }

        AreaBalance::query()->updateOrCreate(
            ['area_id' => $class->area_id, 'student_id' => $student->id],
            ['auras' => $auras],
        );
    }
}
