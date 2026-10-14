<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\CosmeticListing;
use App\Models\Enrollment;
use App\Models\EnrollmentCosmetic;
use App\Models\EnrollmentPet;
use App\Models\ForgeLedger;
use App\Models\ForgeRaffle;
use App\Models\ForgeVault;
use App\Models\GameCurrency;
use App\Models\Pet;
use App\Models\PetListing;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CosmeticCatalog;
use App\Support\ForgeCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ForgeService
{
    /**
     * @return array{
     *     available: bool,
     *     cosmetics: list<array<string, mixed>>,
     *     pets: list<array<string, mixed>>,
     *     vault: ?ForgeVault
     * }
     */
    public function forgeData(User $student, SchoolClass $class): array
    {
        if (! $class->area_id) {
            return [
                'available' => false,
                'cosmetics' => [],
                'pets' => [],
                'vault' => null,
            ];
        }

        $enrollment = $student->enrollmentIn($class);
        if (! $enrollment) {
            throw ValidationException::withMessages([
                'forge' => 'Você não está matriculado nesta turma.',
            ]);
        }

        $enrollment->loadMissing(['cosmetics', 'pets.pet', 'pets.listing']);

        $listedCosmeticIds = CosmeticListing::query()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('price', 'enrollment_cosmetic_id');

        $cosmetics = $enrollment->cosmetics
            ->map(fn (EnrollmentCosmetic $row) => $row->toInventoryArray(
                equipped: $enrollment->isCosmeticEquipped((int) $row->id),
                listedPrice: isset($listedCosmeticIds[$row->id]) ? (int) $listedCosmeticIds[$row->id] : null,
            ))
            ->values()
            ->all();

        $pets = $enrollment->pets
            ->map(fn (EnrollmentPet $row) => $row->toInventoryArray(
                equipped: (int) $enrollment->equipped_pet_id === (int) $row->id,
                listedPrice: $row->listing?->price,
            ))
            ->values()
            ->all();

        return [
            'available' => true,
            'cosmetics' => $cosmetics,
            'pets' => $pets,
            'vault' => ForgeVault::forArea($class->area),
        ];
    }

    /**
     * @param  list<int>  $enrollmentCosmeticIds
     */
    public function forgeCosmetics(User $student, SchoolClass $class, array $enrollmentCosmeticIds): EnrollmentCosmetic
    {
        $this->assertRealm($class);
        $ids = $this->normalizeIds($enrollmentCosmeticIds);

        if (count($ids) < ForgeCatalog::MIN_INPUTS) {
            throw ValidationException::withMessages([
                'cosmetic_ids' => 'Selecione pelo menos '.ForgeCatalog::MIN_INPUTS.' cosméticos iguais para forjar.',
            ]);
        }

        return DB::transaction(function () use ($student, $class, $ids) {
            $enrollment = $this->lockedEnrollment($student, $class);

            $inputs = EnrollmentCosmetic::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($inputs->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'cosmetic_ids' => 'Um ou mais cosméticos selecionados não pertencem a você.',
                ]);
            }

            $itemKey = (string) $inputs->first()->item_key;
            if ($inputs->contains(fn (EnrollmentCosmetic $row) => $row->item_key !== $itemKey)) {
                throw ValidationException::withMessages([
                    'cosmetic_ids' => 'Só é possível forjar cópias do mesmo item.',
                ]);
            }

            if (CosmeticListing::query()->whereIn('enrollment_cosmetic_id', $ids)->exists()) {
                throw ValidationException::withMessages([
                    'cosmetic_ids' => 'Retire os anúncios dos itens antes de forjar.',
                ]);
            }

            $catalogItem = CosmeticCatalog::item($itemKey);
            if (! $catalogItem) {
                throw ValidationException::withMessages([
                    'cosmetic_ids' => 'Item de catálogo inválido.',
                ]);
            }

            $unitPrice = (int) ($catalogItem['price'] ?? 0);
            $catalogSum = $unitPrice * $inputs->count();
            $fee = ForgeCatalog::calculateFee($catalogSum);
            $currency = CosmeticCatalog::currency($itemKey);

            $this->debitCurrency($student, $class, $enrollment, $currency, $fee, 'fee');

            $resultLevel = (int) $inputs->sum(fn (EnrollmentCosmetic $row) => (int) $row->forge_level);
            $resultBonus = round((float) $inputs->sum(fn (EnrollmentCosmetic $row) => $row->effectiveCombatBonus()), 4);
            $baseTone = $catalogItem['css'] ?? null;
            $forgeTone = ForgeCatalog::toneForCosmeticLevel($resultLevel, is_string($baseTone) ? $baseTone : null);

            foreach ($inputs as $input) {
                $enrollment->clearEquippedCosmeticId((int) $input->id);
            }
            $enrollment->save();

            EnrollmentCosmetic::query()->whereIn('id', $ids)->delete();

            $result = EnrollmentCosmetic::query()->create([
                'enrollment_id' => $enrollment->id,
                'item_key' => $itemKey,
                'forge_level' => $resultLevel,
                'combat_bonus' => $resultBonus,
                'forge_tone' => $forgeTone,
            ]);

            $vault = $this->lockedVault($class->area);
            $vault->deposit($currency, $fee);

            ForgeLedger::query()->create([
                'area_id' => $class->area_id,
                'vault_id' => $vault->id,
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'kind' => ForgeLedger::KIND_COSMETIC,
                'source_key' => $itemKey,
                'input_count' => $inputs->count(),
                'result_level' => $resultLevel,
                'result_combat_bonus' => $resultBonus,
                'result_cosmetic_id' => $result->id,
                'result_pet_id' => null,
                'fee_currency' => $currency,
                'fee_amount' => $fee,
                'created_at' => now(),
            ]);

            return $result;
        });
    }

    /**
     * @param  list<int>  $enrollmentPetIds
     */
    public function forgePets(User $student, SchoolClass $class, array $enrollmentPetIds): EnrollmentPet
    {
        $this->assertRealm($class);
        $ids = $this->normalizeIds($enrollmentPetIds);

        if (count($ids) < ForgeCatalog::MIN_INPUTS) {
            throw ValidationException::withMessages([
                'pet_ids' => 'Selecione pelo menos '.ForgeCatalog::MIN_INPUTS.' mascotes iguais para forjar.',
            ]);
        }

        return DB::transaction(function () use ($student, $class, $ids) {
            $enrollment = $this->lockedEnrollment($student, $class);

            $inputs = EnrollmentPet::query()
                ->with('pet')
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($inputs->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'pet_ids' => 'Um ou mais mascotes selecionados não pertencem a você.',
                ]);
            }

            $petId = (int) $inputs->first()->pet_id;
            if ($inputs->contains(fn (EnrollmentPet $row) => (int) $row->pet_id !== $petId)) {
                throw ValidationException::withMessages([
                    'pet_ids' => 'Só é possível forjar cópias do mesmo mascote.',
                ]);
            }

            if (PetListing::query()->whereIn('enrollment_pet_id', $ids)->exists()) {
                throw ValidationException::withMessages([
                    'pet_ids' => 'Retire os anúncios dos mascotes antes de forjar.',
                ]);
            }

            $pet = Pet::query()->whereKey($petId)->first();
            if (! $pet) {
                throw ValidationException::withMessages([
                    'pet_ids' => 'Mascote de catálogo inválido.',
                ]);
            }

            [$currency, $unitPrice] = $this->petCatalogPrice($pet);
            $catalogSum = $unitPrice * $inputs->count();
            $fee = ForgeCatalog::calculateFee($catalogSum);

            $this->debitCurrency($student, $class, $enrollment, $currency, $fee, 'fee');

            $resultLevel = (int) $inputs->sum(fn (EnrollmentPet $row) => (int) $row->forge_level);
            $resultBonus = round((float) $inputs->sum(fn (EnrollmentPet $row) => $row->effectiveCombatBonus()), 4);
            $baseAura = (string) $inputs->first()->aura_color;
            $resultAura = ForgeCatalog::auraForPetLevel($resultLevel, $baseAura);
            $customName = (string) $inputs->first()->custom_name;

            if (in_array((int) $enrollment->equipped_pet_id, $ids, true)) {
                $enrollment->equipped_pet_id = null;
            }
            $enrollment->save();

            EnrollmentPet::query()->whereIn('id', $ids)->delete();

            $result = EnrollmentPet::query()->create([
                'enrollment_id' => $enrollment->id,
                'pet_id' => $petId,
                'custom_name' => $customName,
                'aura_color' => $resultAura,
                'forge_level' => $resultLevel,
                'combat_bonus' => $resultBonus,
            ]);

            $vault = $this->lockedVault($class->area);
            $vault->deposit($currency, $fee);

            ForgeLedger::query()->create([
                'area_id' => $class->area_id,
                'vault_id' => $vault->id,
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->id,
                'kind' => ForgeLedger::KIND_PET,
                'source_key' => (string) ($pet->species_key ?: $pet->id),
                'input_count' => $inputs->count(),
                'result_level' => $resultLevel,
                'result_combat_bonus' => $resultBonus,
                'result_cosmetic_id' => null,
                'result_pet_id' => $result->id,
                'fee_currency' => $currency,
                'fee_amount' => $fee,
                'created_at' => now(),
            ]);

            return $result->load('pet');
        });
    }

    /**
     * @return Collection<int, array{area: Area, vault: ForgeVault, eligible_count: int, raffles: Collection<int, ForgeRaffle>}>
     */
    public function vaultsForAdmin(): Collection
    {
        return Area::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Area $area): array {
                $vault = ForgeVault::forArea($area);

                return [
                    'area' => $area,
                    'vault' => $vault,
                    'eligible_count' => $this->eligibleStudentIds($area)->count(),
                    'raffles' => ForgeRaffle::query()
                        ->with('winner')
                        ->where('area_id', $area->id)
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get(),
                ];
            });
    }

    public function raffle(Area $area, User $admin): ForgeRaffle
    {
        return DB::transaction(function () use ($area, $admin) {
            $vault = $this->lockedVault($area);

            if ($vault->isEmpty()) {
                throw ValidationException::withMessages([
                    'raffle' => 'O cofre da forja deste reino está vazio.',
                ]);
            }

            $eligibleIds = $this->eligibleStudentIds($area);
            if ($eligibleIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'raffle' => 'Nenhum aluno elegível para o sorteio neste reino.',
                ]);
            }

            $winnerId = (int) $eligibleIds->random();
            $winner = User::query()->findOrFail($winnerId);
            $winnerClass = $this->classInArea($winner, $area);

            if (! $winnerClass) {
                throw ValidationException::withMessages([
                    'raffle' => 'O sorteado não tem turma neste reino para receber o prêmio.',
                ]);
            }

            $prize = $vault->takeAll();
            $enrollment = $this->lockedEnrollment($winner, $winnerClass);

            foreach (GameCurrency::SHOP_KEYS as $currency) {
                $amount = (int) ($prize[$currency] ?? 0);
                if ($amount > 0) {
                    $this->creditCurrency($winner, $winnerClass, $enrollment, $currency, $amount);
                }
            }
            $enrollment->save();

            return ForgeRaffle::query()->create([
                'area_id' => $area->id,
                'admin_id' => $admin->id,
                'winner_id' => $winner->id,
                'relics' => $prize['relics'],
                'seals' => $prize['seals'],
                'auras' => $prize['auras'],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return Collection<int, int>
     */
    public function eligibleStudentIds(Area $area): Collection
    {
        $since = ForgeRaffle::query()
            ->where('area_id', $area->id)
            ->orderByDesc('id')
            ->value('created_at');

        $query = ForgeLedger::query()->where('area_id', $area->id);
        if ($since) {
            $query->where('created_at', '>', $since);
        }

        return $query->distinct()->pluck('student_id')->values();
    }

    private function assertRealm(SchoolClass $class): void
    {
        if (! $class->area_id) {
            throw ValidationException::withMessages([
                'forge' => 'A forja exige que a turma pertença a um reino.',
            ]);
        }
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function petCatalogPrice(Pet $pet): array
    {
        if ((int) $pet->price_relics > 0) {
            return [GameCurrency::KEY_RELICS, (int) $pet->price_relics];
        }

        if ((int) $pet->price_seals > 0) {
            return [GameCurrency::KEY_SEALS, (int) $pet->price_seals];
        }

        if ((int) $pet->price_auras > 0) {
            return [GameCurrency::KEY_AURAS, (int) $pet->price_auras];
        }

        return [GameCurrency::KEY_RELICS, 1];
    }

    private function lockedEnrollment(User $student, SchoolClass $class): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'forge' => 'Você não está matriculado nesta turma.',
            ]);
        }

        return $enrollment;
    }

    private function lockedVault(Area $area): ForgeVault
    {
        $vault = ForgeVault::forArea($area);

        return ForgeVault::query()->whereKey($vault->id)->lockForUpdate()->firstOrFail();
    }

    private function classInArea(User $student, Area $area): ?SchoolClass
    {
        return $student->classes()
            ->where('classes.area_id', $area->id)
            ->orderBy('classes.name')
            ->first();
    }

    private function debitCurrency(
        User $student,
        SchoolClass $class,
        Enrollment $enrollment,
        string $currency,
        int $amount,
        string $errorKey,
    ): void {
        if ($currency === GameCurrency::KEY_AURAS) {
            $balance = $this->lockedAreaBalance($student, $class);
            if ((int) $balance->auras < $amount) {
                throw ValidationException::withMessages([
                    $errorKey => GameCurrency::label(GameCurrency::KEY_AURAS).' insuficientes para a taxa da forja.',
                ]);
            }

            $balance->auras = (int) $balance->auras - $amount;
            $balance->save();

            return;
        }

        $current = (int) $enrollment->{$currency};
        if ($current < $amount) {
            throw ValidationException::withMessages([
                $errorKey => GameCurrency::label($currency).' insuficientes para a taxa da forja.',
            ]);
        }

        $enrollment->{$currency} = $current - $amount;
        $enrollment->save();
    }

    private function creditCurrency(
        User $student,
        SchoolClass $class,
        Enrollment $enrollment,
        string $currency,
        int $amount,
    ): void {
        if ($currency === GameCurrency::KEY_AURAS) {
            $balance = $this->lockedAreaBalance($student, $class);
            $balance->auras = (int) $balance->auras + $amount;
            $balance->save();

            return;
        }

        $enrollment->{$currency} = (int) $enrollment->{$currency} + $amount;
    }

    private function lockedAreaBalance(User $student, SchoolClass $class): AreaBalance
    {
        $area = $class->area;
        if (! $area instanceof Area) {
            throw ValidationException::withMessages([
                'forge' => 'Esta turma não pertence a um reino para usar Aura.',
            ]);
        }

        $balance = AreaBalance::query()
            ->where('area_id', $area->id)
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = AreaBalance::query()->create([
                'area_id' => $area->id,
                'student_id' => $student->id,
                'auras' => 0,
            ]);
            $balance = AreaBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        return $balance;
    }
}
