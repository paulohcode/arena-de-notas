<?php

namespace Tests\Feature;

use App\Models\ClassCosmeticStock;
use App\Models\EnrollmentCosmetic;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CosmeticCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_admin_shop_redirects_to_login(): void
    {
        $this->get(route('admin.shop.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_admin_shop(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.shop.index'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_cannot_open_admin_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->get(route('admin.shop.index'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_shop_lists_classes_and_remaining_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Loja']);

        $this->actingAs($admin)
            ->get(route('admin.shop.index'))
            ->assertOk()
            ->assertSee('Loja de cosméticos')
            ->assertSee('Turma Loja')
            ->assertSee(count(CosmeticCatalog::ITEMS).' à venda')
            ->assertSee('0 com dono');
    }

    public function test_admin_can_see_owners_and_restock_class_shop(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Loja']);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($admin)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('Estrela Guardiã')
            ->assertSee('Ana Souza')
            ->assertSee('Esgotado na loja');

        $this->actingAs($admin)
            ->post(route('teacher.shop.restock', $class), [
                'item' => 'acc_star',
                'quantity' => 2,
            ])
            ->assertRedirect(route('teacher.shop.show', $class))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('class_cosmetic_stocks', [
            'class_id' => $class->id,
            'item_key' => 'acc_star',
            'quantity' => 2,
        ]);
    }

    public function test_teacher_can_manage_own_class_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Prof']);

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('Turma Prof')
            ->assertSee('Anel de Bronze');
    }

    public function test_teacher_cannot_manage_another_class_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $other = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($other);

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertForbidden();
    }

    public function test_unauthenticated_item_create_redirects_to_login(): void
    {
        $this->post(route('admin.shop.items.store'), $this->itemPayload())
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_create_item_on_admin_shop(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->post(route('admin.shop.items.store'), $this->itemPayload())
            ->assertRedirect(route('teacher.dashboard'));

        $this->assertDatabaseCount('shop_items', 0);
    }

    public function test_student_cannot_create_item_on_admin_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->post(route('admin.shop.items.store'), $this->itemPayload())
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseCount('shop_items', 0);
    }

    public function test_admin_creates_global_item_available_in_class_shops(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Loja']);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);

        $this->actingAs($admin)
            ->post(route('admin.shop.items.store'), $this->itemPayload([
                'name' => 'Capa da Aurora',
                'stock' => 2,
            ]))
            ->assertRedirect(route('admin.shop.index'))
            ->assertSessionHas('success', 'Capa da Aurora criado e disponível em todas as turmas.');

        $item = \App\Models\ShopItem::query()->firstOrFail();
        $this->assertNull($item->class_id);
        $this->assertSame('Capa da Aurora', $item->name);
        $this->assertSame(2, (int) ClassCosmeticStock::query()
            ->where('class_id', $class->id)
            ->where('item_key', $item->item_key)
            ->value('quantity'));

        $this->actingAs($admin)
            ->get(route('admin.shop.index'))
            ->assertOk()
            ->assertSee('Capa da Aurora')
            ->assertSee('todas as turmas');

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('Capa da Aurora');

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Capa da Aurora');
    }

    public function test_empty_item_payload_returns_required_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->from(route('admin.shop.index'))
            ->post(route('admin.shop.items.store'), [])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'name' => 'Informe o nome do item.',
                'slot' => 'Escolha o tipo do item.',
                'price' => 'Informe o preço do item.',
                'currency' => 'Escolha a moeda do item.',
                'rarity' => 'Escolha a raridade do item.',
                'icon' => 'Informe um ícone para o item.',
            ]);

        $this->assertDatabaseCount('shop_items', 0);
    }

    public function test_teacher_creates_item_only_for_own_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Prof']);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $otherClass = $this->createClassForTeacher($otherTeacher, ['name' => 'Outra Turma']);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 80);
        $otherStudent = $this->enrollStudent($otherClass, 'Bruno Lima', relics: 80);

        $this->actingAs($teacher)
            ->post(route('teacher.shop.items.store', $class), $this->itemPayload([
                'name' => 'Estrela da Turma',
                'price' => 20,
                'stock' => 1,
            ]))
            ->assertRedirect(route('teacher.shop.show', $class))
            ->assertSessionHas('success', 'Estrela da Turma cadastrado na loja desta turma.');

        $item = \App\Models\ShopItem::query()->firstOrFail();
        $this->assertSame($class->id, $item->class_id);

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Estrela da Turma');

        $this->actingAs($otherStudent)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertDontSee('Estrela da Turma');

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => $item->item_key])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $this->assertTrue($student->enrollmentIn($class)->ownsCosmetic($item->item_key));
    }

    public function test_another_teacher_cannot_create_item_for_the_class(): void
    {
        $owner = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($owner);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.shop.items.store', $class), $this->itemPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('shop_items', 0);
    }

    public function test_escapes_dangerous_custom_item_name(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);

        $this->actingAs($teacher)
            ->post(route('teacher.shop.items.store', $class), $this->itemPayload([
                'name' => "<script>alert('xss')</script>",
            ]))
            ->assertRedirect(route('teacher.shop.show', $class));

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_escapes_dangerous_owner_name_on_staff_shop(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, "<script>alert('xss')</script>", relics: 100);

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star']);

        $this->actingAs($admin)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;', false)
            ->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_sold_out_item_cannot_be_bought_from_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $ana = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $bruno = $this->enrollStudent($class, 'Bruno Lima', relics: 100);

        $this->actingAs($ana)
            ->post(route('student.shop.purchase'), ['item' => 'acc_star'])
            ->assertRedirect(route('student.shop.index'));

        $this->actingAs($bruno)
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => 'acc_star'])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertSame(1, EnrollmentCosmetic::query()->where('item_key', 'acc_star')->count());
        $this->assertSame(0, (int) ClassCosmeticStock::query()->where('class_id', $class->id)->where('item_key', 'acc_star')->value('quantity'));
        $this->assertFalse($bruno->enrollmentIn($class)->ownsCosmetic('acc_star'));
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Capa da Turma',
            'slot' => CosmeticCatalog::SLOT_ACCESSORY,
            'price' => 45,
            'currency' => CosmeticCatalog::CURRENCY_RELICS,
            'rarity' => 'uncommon',
            'icon' => '🧣',
            'css' => 'ember',
            'stock' => 1,
        ], $overrides);
    }
}
