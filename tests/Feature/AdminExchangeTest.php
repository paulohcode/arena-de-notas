<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_exchange_page_redirects_to_login(): void
    {
        $this->get(route('admin.exchange.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_exchange_cadastro(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.exchange.index'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_cannot_open_exchange_cadastro(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->get(route('admin.exchange.index'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_dashboard_and_nav_link_to_casa_de_cambio(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Casa de Câmbio')
            ->assertSee(route('admin.exchange.index'), false);

        $this->actingAs($admin)
            ->get(route('admin.exchange.index'))
            ->assertOk()
            ->assertSee('Casa de Câmbio')
            ->assertSee('Comprar')
            ->assertSee('Aura');
    }

    public function test_admin_index_ensures_default_offers_for_all_shop_currencies(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.exchange.index'))
            ->assertOk();

        $this->assertSame(6, ExchangeRate::query()->count());
        $this->assertTrue(
            ExchangeRate::query()
                ->where('pay_currency', 'relics')
                ->where('receive_currency', 'auras')
                ->where('pay_amount', 15)
                ->where('receive_amount', 100)
                ->exists()
        );
        $this->assertTrue(
            ExchangeRate::query()
                ->where('pay_currency', 'seals')
                ->where('receive_currency', 'auras')
                ->where('pay_amount', 5)
                ->where('receive_amount', 100)
                ->exists()
        );
        $this->assertTrue(
            ExchangeRate::query()
                ->where('pay_currency', 'seals')
                ->where('receive_currency', 'relics')
                ->exists()
        );
        $this->assertTrue(
            ExchangeRate::query()
                ->where('pay_currency', 'auras')
                ->where('receive_currency', 'relics')
                ->exists()
        );
        $this->assertTrue(
            ExchangeRate::query()
                ->where('pay_currency', 'relics')
                ->where('receive_currency', 'seals')
                ->exists()
        );
        $this->assertTrue(
            ExchangeRate::query()
                ->where('pay_currency', 'auras')
                ->where('receive_currency', 'seals')
                ->exists()
        );
    }

    public function test_admin_creates_custom_offer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        ExchangeRate::query()->delete();

        $this->actingAs($admin)
            ->post(route('admin.exchange.store'), [
                'receive_currency' => 'auras',
                'receive_amount' => 100,
                'pay_currency' => 'relics',
                'pay_amount' => 15,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $rate = ExchangeRate::query()->firstOrFail();
        $this->assertSame('relics', $rate->pay_currency);
        $this->assertSame(15, (int) $rate->pay_amount);
        $this->assertSame('auras', $rate->receive_currency);
        $this->assertSame(100, (int) $rate->receive_amount);
        $this->assertTrue($rate->is_active);
    }

    public function test_admin_rejects_same_currency_on_both_sides(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->from(route('admin.exchange.index'))
            ->post(route('admin.exchange.store'), [
                'receive_currency' => 'relics',
                'receive_amount' => 10,
                'pay_currency' => 'relics',
                'pay_amount' => 10,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHasErrors(['pay_currency']);
    }

    public function test_admin_rejects_duplicate_pay_receive_pair(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        ExchangeRate::ensureShopOffers();

        $this->actingAs($admin)
            ->from(route('admin.exchange.index'))
            ->post(route('admin.exchange.store'), [
                'receive_currency' => 'auras',
                'receive_amount' => 50,
                'pay_currency' => 'relics',
                'pay_amount' => 8,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHasErrors(['pay_currency']);
    }

    public function test_admin_updates_and_deactivates_offer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        ExchangeRate::ensureShopOffers();
        $rate = ExchangeRate::query()
            ->where('pay_currency', 'relics')
            ->where('receive_currency', 'auras')
            ->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.exchange.update', $rate), [
                'receive_currency' => 'auras',
                'receive_amount' => 120,
                'pay_currency' => 'relics',
                'pay_amount' => 18,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $rate->refresh();
        $this->assertSame(18, (int) $rate->pay_amount);
        $this->assertSame(120, (int) $rate->receive_amount);
        $this->assertFalse($rate->is_active);
    }

    public function test_admin_deletes_offer(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        ExchangeRate::ensureShopOffers();
        $rate = ExchangeRate::query()
            ->where('pay_currency', 'seals')
            ->where('receive_currency', 'auras')
            ->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.exchange.destroy', $rate))
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exchange_rates', ['id' => $rate->id]);
    }

    private function enrollStudent(SchoolClass $class, string $name): User
    {
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
