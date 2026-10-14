<?php

namespace Tests\Feature;

use App\Models\EnrollmentCosmetic;
use App\Models\EnrollmentPet;
use App\Models\ForgeLedger;
use App\Models\ForgeRaffle;
use App\Models\ForgeVault;
use App\Models\Pet;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CosmeticCatalog;
use App\Support\ForgeCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_forge_three_identical_cosmetics(): void
    {
        [$class, $student] = $this->studentInClass(relics: 100);
        $enrollment = $student->enrollmentIn($class);
        $itemKey = 'acc_star';
        $unitBonus = CosmeticCatalog::combatBonusForKey($itemKey);
        $unitPrice = (int) CosmeticCatalog::item($itemKey)['price'];

        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->giveCosmetic($enrollment, $itemKey)->id;
        }

        $expectedFee = ForgeCatalog::calculateFee($unitPrice * 3);

        $this->actingAs($student)
            ->post(route('student.forge.cosmetics'), ['cosmetic_ids' => $ids])
            ->assertRedirect(route('student.forge.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, EnrollmentCosmetic::query()->where('enrollment_id', $enrollment->id)->count());
        $result = EnrollmentCosmetic::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame(3, (int) $result->forge_level);
        $this->assertEqualsWithDelta($unitBonus * 3, (float) $result->combat_bonus, 0.0001);
        $this->assertNotNull($result->forge_tone);

        $this->assertSame(100 - $expectedFee, (int) $enrollment->fresh()->relics);

        $vault = ForgeVault::query()->where('area_id', $class->area_id)->firstOrFail();
        $this->assertSame($expectedFee, (int) $vault->relics);

        $ledger = ForgeLedger::query()->firstOrFail();
        $this->assertSame(ForgeLedger::KIND_COSMETIC, $ledger->kind);
        $this->assertSame($expectedFee, (int) $ledger->fee_amount);
        $this->assertSame((int) $student->id, (int) $ledger->student_id);
    }

    public function test_reforge_sums_levels_and_power(): void
    {
        [$class, $student] = $this->studentInClass(relics: 50);
        $enrollment = $student->enrollmentIn($class);
        $itemKey = 'frame_bronze';

        $a = $this->giveCosmetic($enrollment, $itemKey, forgeLevel: 2, combatBonus: 0.04);
        $b = $this->giveCosmetic($enrollment, $itemKey, forgeLevel: 1, combatBonus: 0.02);

        $this->actingAs($student)
            ->post(route('student.forge.cosmetics'), ['cosmetic_ids' => [$a->id, $b->id]])
            ->assertRedirect(route('student.forge.index'))
            ->assertSessionHas('success');

        $result = EnrollmentCosmetic::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame(3, (int) $result->forge_level);
        $this->assertEqualsWithDelta(0.06, (float) $result->combat_bonus, 0.0001);
    }

    public function test_forge_rejects_different_item_keys(): void
    {
        [$class, $student] = $this->studentInClass(relics: 50);
        $enrollment = $student->enrollmentIn($class);

        $a = $this->giveCosmetic($enrollment, 'acc_star');
        $b = $this->giveCosmetic($enrollment, 'acc_crown');

        $this->actingAs($student)
            ->from(route('student.forge.index'))
            ->post(route('student.forge.cosmetics'), ['cosmetic_ids' => [$a->id, $b->id]])
            ->assertRedirect(route('student.forge.index'))
            ->assertSessionHasErrors('cosmetic_ids');

        $this->assertSame(2, EnrollmentCosmetic::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_forge_rejects_insufficient_fee_balance(): void
    {
        [$class, $student] = $this->studentInClass(relics: 0);
        $enrollment = $student->enrollmentIn($class);
        $ids = [
            $this->giveCosmetic($enrollment, 'frame_gold')->id,
            $this->giveCosmetic($enrollment, 'frame_gold')->id,
        ];

        $this->actingAs($student)
            ->from(route('student.forge.index'))
            ->post(route('student.forge.cosmetics'), ['cosmetic_ids' => $ids])
            ->assertRedirect(route('student.forge.index'))
            ->assertSessionHasErrors('fee');
    }

    public function test_student_can_forge_identical_pets(): void
    {
        [$class, $student] = $this->studentInClass(relics: 200);
        $enrollment = $student->enrollmentIn($class);
        $pet = Pet::query()->where('class_id', $class->id)->where('active', true)->orderBy('id')->firstOrFail();

        $ids = [];
        for ($i = 0; $i < 2; $i++) {
            $ids[] = EnrollmentPet::query()->create([
                'enrollment_id' => $enrollment->id,
                'pet_id' => $pet->id,
                'custom_name' => 'Pet '.$i,
                'aura_color' => 'ember',
                'forge_level' => 1,
                'combat_bonus' => 0.02,
            ])->id;
        }

        $this->actingAs($student)
            ->post(route('student.forge.pets'), ['pet_ids' => $ids])
            ->assertRedirect(route('student.forge.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, EnrollmentPet::query()->where('enrollment_id', $enrollment->id)->count());
        $result = EnrollmentPet::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame(2, (int) $result->forge_level);
        $this->assertEqualsWithDelta(0.04, (float) $result->combat_bonus, 0.0001);
        $this->assertSame(ForgeCatalog::auraForPetLevel(2, 'ember'), $result->aura_color);
    }

    public function test_forged_cosmetic_combat_bonus_respects_loadout_cap(): void
    {
        [$class, $student] = $this->studentInClass(relics: 50);
        $enrollment = $student->enrollmentIn($class);

        $forged = $this->giveCosmetic($enrollment, 'acc_star', forgeLevel: 5, combatBonus: 0.20);
        $this->equipCosmeticInstance($enrollment, $forged);

        $bonus = CosmeticCatalog::equippedCombatBonus($enrollment->fresh());
        $this->assertSame(0.1, $bonus['bonus']);
    }

    public function test_admin_raffle_pays_eligible_forger_and_empties_vault(): void
    {
        [$class, $student] = $this->studentInClass(relics: 100);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $enrollment = $student->enrollmentIn($class);

        $ids = [
            $this->giveCosmetic($enrollment, 'acc_star')->id,
            $this->giveCosmetic($enrollment, 'acc_star')->id,
        ];

        $this->actingAs($student)
            ->post(route('student.forge.cosmetics'), ['cosmetic_ids' => $ids])
            ->assertSessionHas('success');

        $vault = ForgeVault::query()->where('area_id', $class->area_id)->firstOrFail();
        $prize = (int) $vault->relics;
        $this->assertGreaterThan(0, $prize);

        $before = (int) $enrollment->fresh()->relics;

        $this->actingAs($admin)
            ->post(route('admin.forge.raffle', $class->area))
            ->assertRedirect(route('admin.forge.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, (int) $vault->fresh()->relics);
        $this->assertSame($before + $prize, (int) $enrollment->fresh()->relics);
        $this->assertSame(1, ForgeRaffle::query()->count());
    }

    public function test_admin_raffle_only_includes_forgers_since_last_raffle(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $oldForger = $this->enrollStudent($class, 'Antigo', relics: 100);
        $newForger = $this->enrollStudent($class, 'Novo', relics: 100);

        $oldEnrollment = $oldForger->enrollmentIn($class);
        $this->actingAs($oldForger)
            ->post(route('student.forge.cosmetics'), [
                'cosmetic_ids' => [
                    $this->giveCosmetic($oldEnrollment, 'acc_star')->id,
                    $this->giveCosmetic($oldEnrollment, 'acc_star')->id,
                ],
            ])
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('admin.forge.raffle', $class->area))
            ->assertSessionHas('success');

        $this->travel(2)->seconds();

        ForgeVault::forArea($class->area)->update(['relics' => 10]);

        $newEnrollment = $newForger->enrollmentIn($class);
        $this->actingAs($newForger)
            ->post(route('student.forge.cosmetics'), [
                'cosmetic_ids' => [
                    $this->giveCosmetic($newEnrollment, 'acc_star')->id,
                    $this->giveCosmetic($newEnrollment, 'acc_star')->id,
                ],
            ])
            ->assertSessionHas('success');

        // Força o vault a ter valor conhecido e sorteia: só o novo forjador é elegível.
        $vault = ForgeVault::forArea($class->area);
        $vault->update(['relics' => 15, 'seals' => 0, 'auras' => 0]);

        $beforeNew = (int) $newEnrollment->fresh()->relics;
        $beforeOld = (int) $oldEnrollment->fresh()->relics;

        $this->actingAs($admin)
            ->post(route('admin.forge.raffle', $class->area))
            ->assertSessionHas('success');

        $this->assertSame($beforeNew + 15, (int) $newEnrollment->fresh()->relics);
        $this->assertSame($beforeOld, (int) $oldEnrollment->fresh()->relics);
    }

    public function test_forge_index_requires_auth(): void
    {
        $this->get(route('student.forge.index'))
            ->assertRedirectToRoute('login');
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function studentInClass(string $name = 'Aluno', int $relics = 0, int $seals = 0): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, $name, relics: $relics, seals: $seals);

        return [$class, $student];
    }

    private function enrollStudent(SchoolClass $class, string $name, int $relics = 0, int $seals = 0): User
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
                'seals' => $seals,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }
}
