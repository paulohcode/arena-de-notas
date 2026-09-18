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
            ->assertSee('Casa de Câmbio');
    }

    public function test_admin_creates_exchange_rate_and_normalizes_pair_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->post(route('admin.exchange.store'), [
                'currency_a' => 'seals',
                'currency_b' => 'relics',
                'amount_a' => 10,
                'amount_b' => 17,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $rate = ExchangeRate::query()->firstOrFail();
        $this->assertSame('relics', $rate->currency_a);
        $this->assertSame('seals', $rate->currency_b);
        $this->assertSame(17, (int) $rate->amount_a);
        $this->assertSame(10, (int) $rate->amount_b);
        $this->assertTrue($rate->is_active);
    }

    public function test_admin_rejects_same_currency_on_both_sides(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->from(route('admin.exchange.index'))
            ->post(route('admin.exchange.store'), [
                'currency_a' => 'relics',
                'currency_b' => 'relics',
                'amount_a' => 10,
                'amount_b' => 10,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHasErrors(['currency_b']);

        $this->assertSame(0, ExchangeRate::query()->count());
    }

    public function test_admin_rejects_duplicate_pair_even_when_inverted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        ExchangeRate::query()->create([
            'currency_a' => 'relics',
            'currency_b' => 'seals',
            'amount_a' => 17,
            'amount_b' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.exchange.index'))
            ->post(route('admin.exchange.store'), [
                'currency_a' => 'seals',
                'currency_b' => 'relics',
                'amount_a' => 5,
                'amount_b' => 8,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHasErrors([
                'currency_b' => 'Já existe uma cotação para este par de moedas.',
            ]);

        $this->assertSame(1, ExchangeRate::query()->count());
    }

    public function test_admin_updates_and_deactivates_exchange_rate(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $rate = ExchangeRate::query()->create([
            'currency_a' => 'relics',
            'currency_b' => 'seals',
            'amount_a' => 17,
            'amount_b' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.exchange.update', $rate), [
                'currency_a' => 'relics',
                'currency_b' => 'seals',
                'amount_a' => 12,
                'amount_b' => 10,
            ])
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $rate->refresh();
        $this->assertSame(12, (int) $rate->amount_a);
        $this->assertFalse($rate->is_active);
    }

    public function test_admin_deletes_exchange_rate(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $rate = ExchangeRate::query()->create([
            'currency_a' => 'auras',
            'currency_b' => 'seals',
            'amount_a' => 6,
            'amount_b' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.exchange.destroy', $rate))
            ->assertRedirect(route('admin.exchange.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, ExchangeRate::query()->count());
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
