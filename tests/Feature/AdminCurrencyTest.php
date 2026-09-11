<?php

namespace Tests\Feature;

use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_currencies_page_redirects_to_login(): void
    {
        $this->get(route('admin.currencies.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_currency_cadastro(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.currencies.index'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_cannot_open_currency_cadastro(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->get(route('admin.currencies.index'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_sees_default_currency_names_and_icons(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.currencies.index'))
            ->assertOk()
            ->assertSee('Moedas')
            ->assertSee('Relíquias')
            ->assertSee('Selos')
            ->assertSee('Aura')
            ->assertSee('Glória')
            ->assertSee('💠')
            ->assertSee('💮')
            ->assertSee('✨')
            ->assertSee('🏆');
    }

    public function test_admin_dashboard_links_to_currency_cadastro(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Moedas')
            ->assertSee(route('admin.currencies.index'), false);
    }

    public function test_admin_updates_currency_names_and_icons(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->put(route('admin.currencies.update'), $this->payload([
                'relics' => ['name' => 'Cristais', 'icon' => '💎'],
                'auras' => ['name' => 'Éter', 'icon' => '🌌'],
            ]))
            ->assertRedirect(route('admin.currencies.index'))
            ->assertSessionHas('success', 'Nomes e ícones das moedas atualizados.');

        $this->assertSame('Cristais', GameCurrency::query()->where('key', 'relics')->value('name'));
        $this->assertSame('💎', GameCurrency::query()->where('key', 'relics')->value('icon'));
        $this->assertSame('Éter', GameCurrency::query()->where('key', 'auras')->value('name'));
        $this->assertSame('🌌', GameCurrency::query()->where('key', 'auras')->value('icon'));
        $this->assertSame('Selos', GameCurrency::query()->where('key', 'seals')->value('name'));
        $this->assertSame(4, GameCurrency::query()->count());
    }

    public function test_empty_currency_names_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->from(route('admin.currencies.index'))
            ->put(route('admin.currencies.update'), $this->payload([
                'relics' => ['name' => '', 'icon' => '💎'],
            ]))
            ->assertRedirect(route('admin.currencies.index'))
            ->assertSessionHasErrors([
                'currencies.relics.name' => 'Informe o nome de Relíquias.',
            ]);

        $this->assertSame('Relíquias', GameCurrency::query()->where('key', 'relics')->value('name'));
    }

    public function test_student_shop_shows_updated_currency_name_and_icon(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 42);

        $this->actingAs($admin)
            ->put(route('admin.currencies.update'), $this->payload([
                'relics' => ['name' => 'Cristais', 'icon' => '💎'],
            ]))
            ->assertRedirect(route('admin.currencies.index'));

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('💎')
            ->assertSee('42 Cristais')
            ->assertDontSee('42 Relíquias');
    }

    public function test_escapes_dangerous_currency_name_on_admin_and_shop_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 10);
        $payload = $this->payload([
            'relics' => ['name' => '<script>alert(1)</script>', 'icon' => '<img src=x>'],
        ]);

        $this->actingAs($admin)
            ->put(route('admin.currencies.update'), $payload)
            ->assertRedirect(route('admin.currencies.index'));

        $this->actingAs($admin)
            ->get(route('admin.currencies.index'))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x>', false);

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x>', false);
    }

    /**
     * @param  array<string, array{name?: string, icon?: string}>  $overrides
     * @return array{currencies: array<string, array{name: string, icon: string}>}
     */
    private function payload(array $overrides = []): array
    {
        $currencies = [
            'relics' => ['name' => 'Relíquias', 'icon' => '💠'],
            'seals' => ['name' => 'Selos', 'icon' => '💮'],
            'auras' => ['name' => 'Aura', 'icon' => '✨'],
            'glory' => ['name' => 'Glória', 'icon' => '🏆'],
        ];

        foreach ($overrides as $key => $values) {
            $currencies[$key] = array_merge($currencies[$key], $values);
        }

        return ['currencies' => $currencies];
    }

    private function enrollStudent(SchoolClass $class, string $name, int $relics = 0): User
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
                'relics' => $relics,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }
}
