<?php

namespace Tests\Feature;

use App\Models\AreaBalance;
use App\Models\AreaCosmeticStock;
use App\Models\ClassCosmeticStock;
use App\Models\EnrollmentCosmetic;
use App\Models\SchoolClass;
use App\Models\ShopItem;
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

        $item = ShopItem::query()->firstOrFail();
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
            ->assertSee('todas as turmas')
            ->assertSee('Editar');

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

        $item = ShopItem::query()->firstOrFail();
        $this->assertSame($class->id, $item->class_id);

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('Estrela da Turma')
            ->assertSee('Editar');

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

    public function test_admin_creates_item_with_custom_combat_bonus(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);

        $this->actingAs($admin)
            ->post(route('admin.shop.items.store'), $this->itemPayload([
                'name' => 'Capa da Aurora',
                'rarity' => 'common',
                'combat_bonus_percent' => 5,
                'combat_bonus' => 0.99,
            ]))
            ->assertRedirect(route('admin.shop.index'))
            ->assertSessionHas('success');

        $item = ShopItem::query()->firstOrFail();
        $this->assertSame(0.05, $item->combat_bonus);

        $this->actingAs($admin)
            ->get(route('admin.shop.index'))
            ->assertOk()
            ->assertSee('+5.0% poder');

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('+5.0% no duelo');

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Capa da Aurora')
            ->assertDontSee('+5.0%');
    }

    public function test_teacher_updates_item_combat_bonus(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $item = ShopItem::factory()->forClass($class->id)->create([
            'name' => 'Estrela da Turma',
            'price' => 20,
            'icon' => '⭐',
            'rarity' => 'common',
            'combat_bonus' => 0.012,
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => 'Estrela da Turma',
                'price' => 20,
                'icon' => '⭐',
                'rarity' => 'common',
                'combat_bonus_percent' => 7,
            ]))
            ->assertRedirect(route('teacher.shop.show', $class));

        $this->assertSame(0.07, $item->fresh()->combat_bonus);

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $class))
            ->assertOk()
            ->assertSee('+7.0% no duelo');
    }

    public function test_combat_bonus_percent_above_ten_returns_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->from(route('admin.shop.index'))
            ->post(route('admin.shop.items.store'), $this->itemPayload([
                'combat_bonus_percent' => 11,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'combat_bonus_percent' => 'O poder do item não pode passar de 10%.',
            ]);

        $this->assertDatabaseCount('shop_items', 0);
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

    public function test_unauthenticated_item_edit_redirects_to_login(): void
    {
        $item = ShopItem::factory()->create();

        $this->get(route('admin.shop.items.edit', $item))
            ->assertRedirectToRoute('login');
    }

    public function test_unauthenticated_item_update_redirects_to_login(): void
    {
        $item = ShopItem::factory()->create(['name' => 'Capa da Turma']);

        $this->put(route('admin.shop.items.update', $item), $this->itemPayload([
            'name' => 'Capa Nova',
        ]))
            ->assertRedirectToRoute('login');

        $this->assertSame('Capa da Turma', $item->fresh()->name);
    }

    public function test_teacher_cannot_update_item_on_admin_shop(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $item = ShopItem::factory()->create(['name' => 'Capa Global']);

        $this->actingAs($teacher)
            ->put(route('admin.shop.items.update', $item), $this->itemPayload([
                'name' => 'Capa Nova',
            ]))
            ->assertRedirect(route('teacher.dashboard'));

        $this->assertSame('Capa Global', $item->fresh()->name);
    }

    public function test_student_cannot_update_item_on_admin_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');
        $item = ShopItem::factory()->create(['name' => 'Capa Global']);

        $this->actingAs($student)
            ->put(route('admin.shop.items.update', $item), $this->itemPayload([
                'name' => 'Capa Nova',
            ]))
            ->assertRedirect(route('student.dashboard'));

        $this->assertSame('Capa Global', $item->fresh()->name);
    }

    public function test_admin_edit_page_shows_current_item_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $item = ShopItem::factory()->create([
            'name' => 'Capa da Aurora',
            'price' => 55,
            'icon' => '🧣',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.items.edit', $item))
            ->assertOk()
            ->assertSee('Editar item')
            ->assertSee('Capa da Aurora')
            ->assertSee('Poder no duelo')
            ->assertSee('Salvar alterações')
            ->assertDontSee('Estoque inicial');
    }

    public function test_admin_updates_global_item_without_changing_item_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $item = ShopItem::factory()->create([
            'name' => 'Capa da Aurora',
            'price' => 45,
            'icon' => '🧣',
        ]);
        $originalKey = $item->item_key;

        $this->actingAs($admin)
            ->put(route('admin.shop.items.update', $item), $this->itemPayload([
                'name' => 'Capa do Sol',
                'price' => 70,
                'item_key' => 'hacked_key',
                'class_id' => $class->id,
            ]))
            ->assertRedirect(route('admin.shop.index'))
            ->assertSessionHas('success', 'Capa do Sol atualizado.');

        $item->refresh();
        $this->assertSame('Capa do Sol', $item->name);
        $this->assertSame(70, $item->price);
        $this->assertSame($originalKey, $item->item_key);
        $this->assertNull($item->class_id);

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Capa do Sol')
            ->assertDontSee('Capa da Aurora');
    }

    public function test_empty_item_update_payload_returns_required_messages(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $item = ShopItem::factory()->create(['name' => 'Capa da Aurora']);

        $this->actingAs($admin)
            ->from(route('admin.shop.items.edit', $item))
            ->put(route('admin.shop.items.update', $item), [])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'name' => 'Informe o nome do item.',
                'slot' => 'Escolha o tipo do item.',
                'price' => 'Informe o preço do item.',
                'currency' => 'Escolha a moeda do item.',
                'rarity' => 'Escolha a raridade do item.',
                'icon' => 'Informe um ícone para o item.',
            ]);

        $this->assertSame('Capa da Aurora', $item->fresh()->name);
    }

    public function test_teacher_updates_item_only_for_own_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Prof']);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 80);
        $item = ShopItem::factory()->forClass($class->id)->create([
            'name' => 'Estrela da Turma',
            'price' => 20,
            'icon' => '⭐',
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.shop.items.edit', [$class, $item]))
            ->assertOk()
            ->assertSee('Estrela da Turma')
            ->assertSee('Salvar alterações');

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => 'Estrela de Ouro',
                'price' => 30,
                'icon' => '⭐',
            ]))
            ->assertRedirect(route('teacher.shop.show', $class))
            ->assertSessionHas('success', 'Estrela de Ouro atualizado.');

        $this->assertSame('Estrela de Ouro', $item->fresh()->name);

        $this->actingAs($student)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Estrela de Ouro')
            ->assertDontSee('Estrela da Turma');
    }

    public function test_another_teacher_cannot_update_item_for_the_class(): void
    {
        $owner = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($owner);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $item = ShopItem::factory()->forClass($class->id)->create(['name' => 'Estrela da Turma']);

        $this->actingAs($otherTeacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => 'Estrela Roubada',
            ]))
            ->assertForbidden();

        $this->assertSame('Estrela da Turma', $item->fresh()->name);
    }

    public function test_teacher_cannot_update_item_from_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $otherClass = $this->createClassForTeacher($otherTeacher);
        $item = ShopItem::factory()->forClass($otherClass->id)->create(['name' => 'Estrela Alheia']);

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => 'Estrela Roubada',
            ]))
            ->assertNotFound();

        $this->assertSame('Estrela Alheia', $item->fresh()->name);
    }

    public function test_teacher_cannot_update_global_item_on_class_shop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $item = ShopItem::factory()->create(['name' => 'Capa Global']);

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => 'Capa Nova',
            ]))
            ->assertNotFound();

        $this->assertSame('Capa Global', $item->fresh()->name);
    }

    public function test_escapes_dangerous_updated_item_name(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $item = ShopItem::factory()->forClass($class->id)->create([
            'name' => 'Estrela da Turma',
            'icon' => '⭐',
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => "<script>alert('xss')</script>",
                'icon' => '⭐',
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

    public function test_changing_slot_unequips_the_previous_slot(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100);
        $item = ShopItem::factory()->forClass($class->id)->create([
            'name' => 'Estrela da Turma',
            'slot' => CosmeticCatalog::SLOT_ACCESSORY,
            'price' => 20,
            'icon' => '⭐',
        ]);

        $this->actingAs($student)
            ->post(route('student.shop.purchase'), ['item' => $item->item_key])
            ->assertRedirect(route('student.shop.index'));

        $this->actingAs($student)
            ->post(route('student.shop.equip'), ['item' => $item->item_key])
            ->assertRedirect(route('student.shop.index'));

        $this->assertSame($item->item_key, $student->enrollmentIn($class)->fresh()->equipped_accessory);

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$class, $item]), $this->itemPayload([
                'name' => 'Estrela da Turma',
                'slot' => CosmeticCatalog::SLOT_FRAME,
                'price' => 20,
                'icon' => '⭐',
            ]))
            ->assertRedirect(route('teacher.shop.show', $class));

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertNull($enrollment->equipped_accessory);
        $this->assertNull($enrollment->equipped_frame);
        $this->assertTrue($enrollment->ownsCosmetic($item->item_key));
        $this->assertSame(CosmeticCatalog::SLOT_FRAME, $item->fresh()->slot);
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

    public function test_teacher_creates_aura_item_unique_in_the_realm(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma B']);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $otherClass = $this->createClassForTeacher($otherTeacher, ['name' => 'Outro Reino']);
        $studentA = $this->enrollStudent($classA, 'Ana Souza', relics: 80);
        $studentB = $this->enrollStudent($classB, 'Carla Dias', relics: 80);
        $otherStudent = $this->enrollStudent($otherClass, 'Bruno Lima', relics: 80);

        $this->actingAs($teacher)
            ->post(route('teacher.shop.items.store', $classA), $this->itemPayload([
                'name' => 'Cristal de Aura',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
                'price' => 12,
                'stock' => 2,
            ]))
            ->assertRedirect(route('teacher.shop.show', $classA))
            ->assertSessionHas('success', 'Cristal de Aura cadastrado na loja do reino (único para todas as turmas).');

        $item = ShopItem::query()->firstOrFail();
        $this->assertNull($item->class_id);
        $this->assertSame($area->id, $item->area_id);
        $this->assertSame(1, AreaCosmeticStock::query()->where('item_key', $item->item_key)->count());
        $this->assertSame(2, (int) AreaCosmeticStock::query()
            ->where('area_id', $area->id)
            ->where('item_key', $item->item_key)
            ->value('quantity'));
        $this->assertSame(0, ClassCosmeticStock::query()->where('item_key', $item->item_key)->count());

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $classA))
            ->assertOk()
            ->assertSee('Cristal de Aura')
            ->assertSee('único no reino')
            ->assertSee('Editar');

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $classB))
            ->assertOk()
            ->assertSee('Cristal de Aura')
            ->assertSee('2 à venda na loja');

        $this->actingAs($studentA)
            ->withSession(['current_class_id' => $classA->id])
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Cristal de Aura');

        $this->actingAs($studentB)
            ->withSession(['current_class_id' => $classB->id])
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertSee('Cristal de Aura');

        $this->actingAs($otherStudent)
            ->get(route('student.shop.index'))
            ->assertOk()
            ->assertDontSee('Cristal de Aura');
    }

    public function test_buying_aura_item_shares_realm_stock_and_ownership(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma B']);
        $ana = $this->enrollStudent($classA, 'Ana Souza');
        $this->enrollExisting($classB, $ana);
        $bruno = $this->enrollStudent($classB, 'Bruno Lima');

        $this->actingAs($teacher)
            ->post(route('teacher.shop.items.store', $classA), $this->itemPayload([
                'name' => 'Cristal de Aura',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
                'price' => 10,
                'stock' => 1,
            ]));

        $item = ShopItem::query()->firstOrFail();

        AreaBalance::query()->create([
            'area_id' => $area->id,
            'student_id' => $ana->id,
            'auras' => 20,
        ]);
        AreaBalance::query()->create([
            'area_id' => $area->id,
            'student_id' => $bruno->id,
            'auras' => 20,
        ]);

        $this->actingAs($ana)
            ->withSession(['current_class_id' => $classA->id])
            ->post(route('student.shop.purchase'), ['item' => $item->item_key])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, (int) AreaCosmeticStock::query()
            ->where('area_id', $area->id)
            ->where('item_key', $item->item_key)
            ->value('quantity'));
        $this->assertTrue($ana->enrollmentIn($classA)->ownsCosmetic($item->item_key));
        $this->assertFalse($ana->enrollmentIn($classB)->ownsCosmetic($item->item_key));

        $this->actingAs($ana)
            ->withSession(['current_class_id' => $classB->id])
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => $item->item_key])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->actingAs($bruno)
            ->withSession(['current_class_id' => $classB->id])
            ->from(route('student.shop.index'))
            ->post(route('student.shop.purchase'), ['item' => $item->item_key])
            ->assertRedirect(route('student.shop.index'))
            ->assertSessionHasErrors('item');

        $this->assertFalse($bruno->enrollmentIn($classB)->ownsCosmetic($item->item_key));
    }

    public function test_admin_creates_aura_item_with_one_stock_per_realm(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $areaA = $this->createAreaForTeacher($teacher, ['name' => 'Reino Norte']);
        $areaB = $this->createAreaForTeacher($teacher, ['name' => 'Reino Sul']);
        $classA1 = $this->createClassForTeacher($teacher, ['area' => $areaA, 'name' => 'Turma Norte 1']);
        $classA2 = $this->createClassForTeacher($teacher, ['area' => $areaA, 'name' => 'Turma Norte 2']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $areaB, 'name' => 'Turma Sul']);

        $this->actingAs($admin)
            ->post(route('admin.shop.items.store'), $this->itemPayload([
                'name' => 'Manto de Aura',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
                'stock' => 3,
            ]))
            ->assertRedirect(route('admin.shop.index'))
            ->assertSessionHas('success', 'Manto de Aura criado: único em cada reino.');

        $item = ShopItem::query()->firstOrFail();
        $this->assertNull($item->class_id);
        $this->assertNull($item->area_id);
        $this->assertSame(0, ClassCosmeticStock::query()->where('item_key', $item->item_key)->count());
        $this->assertSame(1, AreaCosmeticStock::query()->where('area_id', $areaA->id)->where('item_key', $item->item_key)->count());
        $this->assertSame(1, AreaCosmeticStock::query()->where('area_id', $areaB->id)->where('item_key', $item->item_key)->count());
        $this->assertSame(3, (int) AreaCosmeticStock::query()->where('area_id', $areaA->id)->where('item_key', $item->item_key)->value('quantity'));
        $this->assertSame(3, (int) AreaCosmeticStock::query()->where('area_id', $areaB->id)->where('item_key', $item->item_key)->value('quantity'));

        $this->actingAs($admin)
            ->get(route('admin.shop.index'))
            ->assertOk()
            ->assertSee('Manto de Aura')
            ->assertSee('único em cada reino');

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $classA1))
            ->assertOk()
            ->assertSee('Manto de Aura')
            ->assertSee('3 à venda na loja');

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $classA2))
            ->assertOk()
            ->assertSee('3 à venda na loja');

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $classB))
            ->assertOk()
            ->assertSee('Manto de Aura');
    }

    public function test_restock_aura_item_updates_shared_realm_stock(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma B']);

        $this->actingAs($teacher)
            ->post(route('teacher.shop.items.store', $classA), $this->itemPayload([
                'name' => 'Cristal de Aura',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
                'stock' => 1,
            ]));

        $item = ShopItem::query()->firstOrFail();

        $this->actingAs($teacher)
            ->post(route('teacher.shop.restock', $classB), [
                'item' => $item->item_key,
                'quantity' => 4,
            ])
            ->assertRedirect(route('teacher.shop.show', $classB));

        $this->assertSame(4, (int) AreaCosmeticStock::query()
            ->where('area_id', $area->id)
            ->where('item_key', $item->item_key)
            ->value('quantity'));
        $this->assertSame(1, AreaCosmeticStock::query()->where('item_key', $item->item_key)->count());

        $this->actingAs($teacher)
            ->get(route('teacher.shop.show', $classA))
            ->assertOk()
            ->assertSee('4 à venda na loja');
    }

    public function test_teacher_can_edit_aura_item_from_another_class_in_the_same_realm(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $area, 'name' => 'Turma B']);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $otherClass = $this->createClassForTeacher($otherTeacher, ['name' => 'Outro Reino']);

        $this->actingAs($teacher)
            ->post(route('teacher.shop.items.store', $classA), $this->itemPayload([
                'name' => 'Cristal de Aura',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
            ]));

        $item = ShopItem::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher.shop.items.update', [$classB, $item]), $this->itemPayload([
                'name' => 'Cristal do Reino',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
            ]))
            ->assertRedirect(route('teacher.shop.show', $classB))
            ->assertSessionHas('success', 'Cristal do Reino atualizado.');

        $this->assertSame('Cristal do Reino', $item->fresh()->name);
        $this->assertNull($item->fresh()->class_id);
        $this->assertSame($area->id, $item->fresh()->area_id);

        $this->actingAs($otherTeacher)
            ->put(route('teacher.shop.items.update', [$otherClass, $item]), $this->itemPayload([
                'name' => 'Cristal Roubado',
                'currency' => CosmeticCatalog::CURRENCY_AURAS,
            ]))
            ->assertNotFound();

        $this->assertSame('Cristal do Reino', $item->fresh()->name);
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

    private function enrollExisting(SchoolClass $class, User $student, int $relics = 0): User
    {
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
