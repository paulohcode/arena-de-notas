<?php

namespace Tests\Feature;

use App\Models\AreaBalance;
use App\Models\EnrollmentPet;
use App\Models\Pet;
use App\Models\PetListing;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ArenaCombatService;
use App\Services\PetShopService;
use App\Support\PetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PetShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_pets_redirects_to_login(): void
    {
        $this->get(route('student.pets.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_seed_creates_species_only_for_the_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['name' => 'Turma B']);

        $this->assertSame(count(PetCatalog::SPECIES), Pet::query()->where('class_id', $classA->id)->count());
        $this->assertSame(count(PetCatalog::SPECIES), Pet::query()->where('class_id', $classB->id)->count());
        $this->assertNotSame(
            Pet::query()->where('class_id', $classA->id)->where('species_key', 'owl_sage')->value('id'),
            Pet::query()->where('class_id', $classB->id)->where('species_key', 'owl_sage')->value('id'),
        );
    }

    public function test_student_can_purchase_and_equip_pet(): void
    {
        [$class, $student] = $this->readyStudentWithBalances(relics: 500, seals: 50, auras: 500);
        $pet = Pet::query()->where('class_id', $class->id)->where('species_key', 'owl_sage')->firstOrFail();

        $this->actingAs($student)
            ->post(route('student.pets.purchase'), [
                'pet_id' => $pet->id,
                'custom_name' => 'Athena',
                'aura_color' => 'ember',
            ])
            ->assertRedirect(route('student.pets.index'))
            ->assertSessionHas('success');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(500 - 280, (int) $enrollment->relics);
        $this->assertSame(50 - 40, (int) $enrollment->seals);
        $this->assertSame(500 - 280, $this->auras($student, $class));

        $owned = EnrollmentPet::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame('Athena', $owned->custom_name);
        $this->assertSame('ember', $owned->aura_color);

        $this->actingAs($student)
            ->post(route('student.pets.equip'), ['enrollment_pet_id' => $owned->id])
            ->assertRedirect(route('student.pets.index'));

        $this->assertSame($owned->id, (int) $enrollment->fresh()->equipped_pet_id);
    }

    public function test_purchase_rejects_missing_currency(): void
    {
        [$class, $student] = $this->readyStudentWithBalances(relics: 500, seals: 50, auras: 10);
        $pet = Pet::query()->where('class_id', $class->id)->where('species_key', 'owl_sage')->firstOrFail();

        $this->actingAs($student)
            ->from(route('student.pets.index'))
            ->post(route('student.pets.purchase'), [
                'pet_id' => $pet->id,
                'custom_name' => 'Athena',
                'aura_color' => 'frost',
            ])
            ->assertRedirect(route('student.pets.index'))
            ->assertSessionHasErrors('pet_id');

        $this->assertSame(0, EnrollmentPet::query()->count());
        $this->assertSame(500, (int) $student->enrollmentIn($class)->fresh()->relics);
    }

    public function test_cannot_buy_pet_from_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'A']);
        $classB = $this->createClassForTeacher($teacher, ['name' => 'B']);
        $student = $this->enrollStudent($classA, 'Ana Souza', relics: 2000, seals: 200);
        $this->setAuras($student, $classA, 2000);

        $petB = Pet::query()->where('class_id', $classB->id)->where('species_key', 'owl_sage')->firstOrFail();

        $this->actingAs($student)
            ->withSession(['current_class_id' => $classA->id])
            ->from(route('student.pets.index'))
            ->post(route('student.pets.purchase'), [
                'pet_id' => $petB->id,
                'custom_name' => 'Intruso',
                'aura_color' => 'storm',
            ])
            ->assertRedirect(route('student.pets.index'))
            ->assertSessionHasErrors('pet_id');

        $this->assertSame(0, EnrollmentPet::query()->count());
    }

    public function test_equip_replaces_previous_pet(): void
    {
        [$class, $student] = $this->readyStudentWithBalances(relics: 2000, seals: 200, auras: 2000);
        $enrollment = $student->enrollmentIn($class);
        $owl = Pet::query()->where('class_id', $class->id)->where('species_key', 'owl_sage')->firstOrFail();
        $wolf = Pet::query()->where('class_id', $class->id)->where('species_key', 'rune_wolf')->firstOrFail();

        $first = EnrollmentPet::query()->create([
            'enrollment_id' => $enrollment->id,
            'pet_id' => $owl->id,
            'custom_name' => 'Uno',
            'aura_color' => 'ember',
        ]);
        $second = EnrollmentPet::query()->create([
            'enrollment_id' => $enrollment->id,
            'pet_id' => $wolf->id,
            'custom_name' => 'Dos',
            'aura_color' => 'frost',
        ]);

        $enrollment->update(['equipped_pet_id' => $first->id]);

        $this->actingAs($student)
            ->post(route('student.pets.equip'), ['enrollment_pet_id' => $second->id])
            ->assertRedirect(route('student.pets.index'));

        $this->assertSame($second->id, (int) $enrollment->fresh()->equipped_pet_id);
    }

    public function test_market_transfers_named_pet_and_unequips_seller(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $seller = $this->enrollStudent($class, 'Ana Souza', relics: 100, seals: 0);
        $buyer = $this->enrollStudent($class, 'Bruno Lima', relics: 500, seals: 0, characterClass: 'mago');

        $pet = Pet::query()->where('class_id', $class->id)->where('species_key', 'owl_sage')->firstOrFail();
        $sellerEnrollment = $seller->enrollmentIn($class);
        $owned = EnrollmentPet::query()->create([
            'enrollment_id' => $sellerEnrollment->id,
            'pet_id' => $pet->id,
            'custom_name' => 'Fúria',
            'aura_color' => 'bloom',
        ]);
        $sellerEnrollment->update(['equipped_pet_id' => $owned->id]);

        $this->actingAs($seller)
            ->post(route('student.pets.list'), [
                'enrollment_pet_id' => $owned->id,
                'price' => 80,
            ])
            ->assertRedirect(route('student.pets.index'));

        $this->assertNull($sellerEnrollment->fresh()->equipped_pet_id);
        $listing = PetListing::query()->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('student.pets.listings.buy', $listing))
            ->assertRedirect(route('student.pets.index'));

        $owned->refresh();
        $this->assertSame($buyer->enrollmentIn($class)->id, (int) $owned->enrollment_id);
        $this->assertSame('Fúria', $owned->custom_name);
        $this->assertSame('bloom', $owned->aura_color);
        $this->assertSame(420, (int) $buyer->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(180, (int) $seller->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, PetListing::query()->count());
    }

    public function test_equipped_pet_appears_on_student_sheet(): void
    {
        [$class, $student] = $this->readyStudentWithBalances(relics: 500, seals: 50, auras: 500);
        $enrollment = $student->enrollmentIn($class);
        $pet = Pet::query()->where('class_id', $class->id)->where('species_key', 'owl_sage')->firstOrFail();
        $owned = EnrollmentPet::query()->create([
            'enrollment_id' => $enrollment->id,
            'pet_id' => $pet->id,
            'custom_name' => 'Aurora',
            'aura_color' => 'aurora',
        ]);
        $enrollment->update(['equipped_pet_id' => $owned->id]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Aurora')
            ->assertSee('Mascote');
    }

    public function test_equipped_pet_increases_combat_power(): void
    {
        [$class, $student] = $this->readyStudentWithBalances(relics: 500, seals: 50, auras: 500);
        $enrollment = $student->enrollmentIn($class);
        $pet = Pet::query()->where('class_id', $class->id)->where('species_key', 'owl_sage')->firstOrFail();
        $owned = EnrollmentPet::query()->create([
            'enrollment_id' => $enrollment->id,
            'pet_id' => $pet->id,
            'custom_name' => 'Poder',
            'aura_color' => 'storm',
        ]);

        $combat = app(ArenaCombatService::class);
        $before = $combat->buildFighter($student, $class);

        $enrollment->update(['equipped_pet_id' => $owned->id]);
        $after = $combat->buildFighter($student->fresh(), $class);

        $this->assertSame(0.0, (float) ($before['breakdown']['pet_bonus'] ?? 0));
        $this->assertSame(0.02, (float) $after['breakdown']['pet_bonus']);
        $this->assertSame('Poder', $after['pet_name']);
        $this->assertGreaterThan($before['power'], $after['power']);
    }

    public function test_teacher_can_create_and_edit_pet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.pets.store', $class), [
                'name' => 'Gato Lunar',
                'description' => 'Mia na lua cheia',
                'rarity' => 'rare',
                'sprite_key' => 'fox',
                'price_relics' => 400,
                'price_seals' => 50,
                'price_auras' => 400,
                'combat_bonus_percent' => 4,
                'stock' => 3,
            ])
            ->assertRedirect(route('teacher.pets.show', $class));

        $pet = Pet::query()->where('class_id', $class->id)->where('name', 'Gato Lunar')->firstOrFail();
        $this->assertSame(400, (int) $pet->price_auras);
        $this->assertSame(0.04, (float) $pet->combat_bonus);

        $this->actingAs($teacher)
            ->put(route('teacher.pets.update', [$class, $pet]), [
                'name' => 'Gato Lunar',
                'description' => 'Mia na lua cheia',
                'rarity' => 'rare',
                'sprite_key' => 'fox',
                'price_relics' => 420,
                'price_seals' => 55,
                'price_auras' => 420,
                'combat_bonus_percent' => 4.5,
                'active' => 1,
            ])
            ->assertRedirect(route('teacher.pets.show', $class));

        $this->assertSame(420, (int) $pet->fresh()->price_relics);
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function readyStudentWithBalances(int $relics = 0, int $seals = 0, int $auras = 0): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: $relics, seals: $seals);
        $this->setAuras($student, $class, $auras);

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
        AreaBalance::query()->updateOrCreate(
            ['area_id' => $class->area_id, 'student_id' => $student->id],
            ['auras' => $auras],
        );
    }

    private function auras(User $student, SchoolClass $class): int
    {
        return app(PetShopService::class)->aurasBalance($student, $class);
    }
}
