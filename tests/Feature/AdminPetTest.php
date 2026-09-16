<?php

namespace Tests\Feature;

use App\Models\Pet;
use App\Models\User;
use App\Support\PetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_pets_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $this->createClassForTeacher($teacher, ['name' => 'Turma Mascotes']);

        $this->actingAs($admin)
            ->get(route('admin.pets.index'))
            ->assertOk()
            ->assertSee('Mascotes')
            ->assertSee('Turma Mascotes');
    }

    public function test_admin_can_create_pet_for_all_classes(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'A']);
        $classB = $this->createClassForTeacher($teacher, ['name' => 'B']);

        $before = Pet::query()->count();

        $this->actingAs($admin)
            ->post(route('admin.pets.store'), [
                'name' => 'Tigre Solar',
                'description' => 'Ruge ao meio-dia',
                'rarity' => 'epic',
                'sprite_key' => 'dragon',
                'price_relics' => 900,
                'price_seals' => 120,
                'price_auras' => 900,
                'combat_bonus_percent' => 6,
                'stock' => 2,
            ])
            ->assertRedirect(route('admin.pets.index'));

        $this->assertSame($before + 2, Pet::query()->count());
        $this->assertSame(1, Pet::query()->where('class_id', $classA->id)->where('name', 'Tigre Solar')->count());
        $this->assertSame(1, Pet::query()->where('class_id', $classB->id)->where('name', 'Tigre Solar')->count());
        $this->assertSame(count(PetCatalog::SPECIES) + 1, Pet::query()->where('class_id', $classA->id)->count());
    }

    public function test_teacher_cannot_open_admin_pets_hub(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);

        $this->actingAs($teacher)
            ->get(route('admin.pets.index'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_admin_can_manage_class_pets_via_teacher_route(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Admin']);

        $this->actingAs($admin)
            ->get(route('teacher.pets.show', $class))
            ->assertOk()
            ->assertSee('Coruja Sábia')
            ->assertSee('280');
    }
}
