<?php

namespace App\Services;

use App\Models\AreaBalance;
use App\Models\Enrollment;
use App\Models\EnrollmentPet;
use App\Models\GameCurrency;
use App\Models\Pet;
use App\Models\PetListing;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\PetCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PetShopService
{
    /**
     * @return array{
     *     enrollment: Enrollment,
     *     catalog: list<array<string, mixed>>,
     *     owned: list<array<string, mixed>>,
     *     equipped: ?array<string, mixed>,
     *     listings: list<array<string, mixed>>,
     *     relics: int,
     *     seals: int,
     *     auras: int
     * }
     */
    public function shopData(User $student, SchoolClass $class): array
    {
        $enrollment = $student->enrollmentIn($class);

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'pets' => 'Você não está matriculado nesta turma.',
            ]);
        }

        $this->seedForClass($class);

        $enrollment->load(['pets.pet', 'equippedPet.pet']);

        $ownedPetIds = $enrollment->pets->pluck('pet_id')->all();
        $ownedSet = array_fill_keys($ownedPetIds, true);
        $equippedId = (int) ($enrollment->equipped_pet_id ?? 0);

        $ownListings = PetListing::query()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('price', 'enrollment_pet_id');

        $owned = $enrollment->pets->map(function (EnrollmentPet $owned) use ($equippedId, $ownListings) {
            return $owned->toInventoryArray(
                equipped: (int) $owned->id === $equippedId,
                listedPrice: $ownListings->get($owned->id),
            );
        })->values()->all();

        $equipped = collect($owned)->firstWhere('equipped', true);

        $catalog = Pet::query()
            ->where('class_id', $class->id)
            ->where('active', true)
            ->orderBy('rarity')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (Pet $pet) use ($ownedSet) {
                $row = $pet->toShopArray();
                $row['owned'] = isset($ownedSet[$pet->id]);

                return $row;
            })
            ->values()
            ->all();

        $listings = PetListing::query()
            ->with(['enrollment.student', 'enrollmentPet.pet'])
            ->where('class_id', $class->id)
            ->where('enrollment_id', '!=', $enrollment->id)
            ->orderBy('price')
            ->orderBy('id')
            ->get()
            ->map(function (PetListing $listing) use ($ownedSet) {
                $instance = $listing->enrollmentPet;
                $pet = $instance?->pet;
                $seller = $listing->enrollment?->student;

                return [
                    'id' => $listing->id,
                    'price' => (int) $listing->price,
                    'custom_name' => $instance?->custom_name ?? 'Mascote',
                    'aura_color' => $instance?->aura_color ?? 'ember',
                    'species_name' => $pet?->name ?? 'Mascote',
                    'sprite_key' => $pet?->spriteKey() ?? 'owl',
                    'gif_url' => $pet?->gifUrl(),
                    'combat_bonus_percent' => $pet?->combatBonusPercent() ?? 0,
                    'seller_id' => $seller?->id,
                    'seller_name' => $seller?->arenaName() ?: ($seller?->name ?? 'Aluno'),
                    'already_owned' => $pet ? isset($ownedSet[$pet->id]) : false,
                ];
            })
            ->values()
            ->all();

        return [
            'enrollment' => $enrollment,
            'catalog' => $catalog,
            'owned' => $owned,
            'equipped' => $equipped,
            'listings' => $listings,
            'relics' => (int) $enrollment->relics,
            'seals' => (int) $enrollment->seals,
            'auras' => $this->aurasBalance($student, $class),
        ];
    }

    public function aurasBalance(User $student, SchoolClass $class): int
    {
        if (! $class->area_id) {
            return 0;
        }

        return (int) (AreaBalance::query()
            ->where('area_id', $class->area_id)
            ->where('student_id', $student->id)
            ->value('auras') ?? 0);
    }

    public function seedForClass(SchoolClass $class): void
    {
        $existing = Pet::query()
            ->where('class_id', $class->id)
            ->whereNotNull('species_key')
            ->pluck('species_key')
            ->all();
        $existingSet = array_fill_keys($existing, true);

        foreach (PetCatalog::SPECIES as $key => $species) {
            if (isset($existingSet[$key])) {
                continue;
            }

            Pet::query()->create([
                'class_id' => $class->id,
                'species_key' => $key,
                'name' => $species['name'],
                'description' => $species['description'],
                'rarity' => $species['rarity'],
                'sprite_key' => $species['sprite_key'],
                'price_relics' => $species['price_relics'],
                'price_seals' => $species['price_seals'],
                'price_auras' => $species['price_auras'],
                'combat_bonus' => $species['combat_bonus'],
                'stock' => $species['stock'],
                'active' => true,
            ]);
        }
    }

    /**
     * @return array{catalog: Collection<int, Pet>, owners: array<int, list<array{student_name: string, custom_name: string, equipped: bool}>>, listings_count: int}
     */
    public function inventory(SchoolClass $class): array
    {
        $this->seedForClass($class);

        $pets = Pet::query()
            ->where('class_id', $class->id)
            ->orderByDesc('active')
            ->orderBy('rarity')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $ownerships = EnrollmentPet::query()
            ->with(['enrollment.student', 'pet'])
            ->whereHas('enrollment', fn ($q) => $q->where('class_id', $class->id))
            ->get();

        $owners = [];
        foreach ($ownerships as $owned) {
            $petId = (int) $owned->pet_id;
            $enrollment = $owned->enrollment;
            $owners[$petId][] = [
                'student_name' => $enrollment?->student?->name ?? 'Aluno',
                'custom_name' => $owned->custom_name,
                'equipped' => (int) ($enrollment?->equipped_pet_id ?? 0) === (int) $owned->id,
            ];
        }

        return [
            'catalog' => $pets,
            'owners' => $owners,
            'listings_count' => PetListing::query()->where('class_id', $class->id)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createItem(array $data, SchoolClass $class, ?UploadedFile $gif = null): Pet
    {
        $this->assertHasPrice($data);

        $path = $gif ? $this->storeGif($gif, $class->id) : null;

        return Pet::query()->create([
            'class_id' => $class->id,
            'species_key' => null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rarity' => $data['rarity'],
            'sprite_key' => $data['sprite_key'] ?? 'owl',
            'gif_path' => $path,
            'price_relics' => (int) $data['price_relics'],
            'price_seals' => (int) $data['price_seals'],
            'price_auras' => (int) $data['price_auras'],
            'combat_bonus' => PetCatalog::percentToBonus($data['combat_bonus_percent'] ?? 2),
            'stock' => (int) ($data['stock'] ?? PetCatalog::DEFAULT_STOCK),
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Pet>
     */
    public function createItemForAllClasses(array $data, ?UploadedFile $gif = null): Collection
    {
        return $this->createItemForClasses(
            $data,
            SchoolClass::query()->orderBy('id')->get(),
            $gif,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  iterable<int, SchoolClass>  $classes
     * @return Collection<int, Pet>
     */
    public function createItemForClasses(array $data, iterable $classes, ?UploadedFile $gif = null): Collection
    {
        $this->assertHasPrice($data);

        $created = collect();
        $firstPath = null;

        foreach ($classes as $class) {
            $path = null;
            if ($gif) {
                if ($firstPath === null) {
                    $firstPath = $this->storeGif($gif, $class->id);
                    $path = $firstPath;
                } else {
                    $path = 'pets/'.$class->id.'/'.basename($firstPath);
                    if (! Storage::disk('public')->copy($firstPath, $path)) {
                        throw ValidationException::withMessages([
                            'gif' => 'Não foi possível salvar o arquivo do mascote. Tente novamente.',
                        ]);
                    }
                }
            }

            $created->push(Pet::query()->create([
                'class_id' => $class->id,
                'species_key' => null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rarity' => $data['rarity'],
                'sprite_key' => $data['sprite_key'] ?? 'owl',
                'gif_path' => $path,
                'price_relics' => (int) $data['price_relics'],
                'price_seals' => (int) $data['price_seals'],
                'price_auras' => (int) $data['price_auras'],
                'combat_bonus' => PetCatalog::percentToBonus($data['combat_bonus_percent'] ?? 2),
                'stock' => (int) ($data['stock'] ?? PetCatalog::DEFAULT_STOCK),
                'active' => true,
            ]));
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(Pet $pet, array $data, ?UploadedFile $gif = null): Pet
    {
        $this->assertHasPrice($data);

        if ($gif instanceof UploadedFile && $gif->isValid()) {
            $newPath = $this->storeGif($gif, (int) $pet->class_id);
            $oldPath = $pet->gif_path;
            $pet->gif_path = $newPath;

            if (filled($oldPath) && $oldPath !== $newPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $pet->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rarity' => $data['rarity'],
            'sprite_key' => $data['sprite_key'] ?? $pet->sprite_key ?? 'owl',
            'price_relics' => (int) $data['price_relics'],
            'price_seals' => (int) $data['price_seals'],
            'price_auras' => (int) $data['price_auras'],
            'combat_bonus' => PetCatalog::percentToBonus($data['combat_bonus_percent'] ?? $pet->combatBonusPercent()),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $pet->active,
        ]);
        $pet->save();

        return $pet;
    }

    public function restock(SchoolClass $class, int $petId, int $quantity): Pet
    {
        $pet = Pet::query()
            ->where('class_id', $class->id)
            ->whereKey($petId)
            ->firstOrFail();

        $pet->stock = max(0, $quantity);
        $pet->save();

        return $pet;
    }

    public function purchase(User $student, SchoolClass $class, int $petId, string $customName, string $auraColor): EnrollmentPet
    {
        if (! array_key_exists($auraColor, PetCatalog::AURA_COLORS)) {
            throw ValidationException::withMessages([
                'aura_color' => 'A cor da aura é inválida.',
            ]);
        }

        $customName = trim($customName);

        return DB::transaction(function () use ($student, $class, $petId, $customName, $auraColor) {
            $pet = Pet::query()
                ->where('class_id', $class->id)
                ->whereKey($petId)
                ->lockForUpdate()
                ->first();

            if (! $pet || ! $pet->active) {
                throw ValidationException::withMessages([
                    'pet_id' => 'Este mascote não está à venda nesta turma.',
                ]);
            }

            if ((int) $pet->stock < 1) {
                throw ValidationException::withMessages([
                    'pet_id' => 'Este mascote esgotou na loja.',
                ]);
            }

            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'pets' => 'Você não está matriculado nesta turma.',
                ]);
            }

            $alreadyOwned = EnrollmentPet::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('pet_id', $pet->id)
                ->exists();

            if ($alreadyOwned) {
                throw ValidationException::withMessages([
                    'pet_id' => 'Você já possui este mascote.',
                ]);
            }

            $this->debitPrices($student, $class, $enrollment, $pet);

            $pet->stock = (int) $pet->stock - 1;
            $pet->save();

            return EnrollmentPet::query()->create([
                'enrollment_id' => $enrollment->id,
                'pet_id' => $pet->id,
                'custom_name' => $customName,
                'aura_color' => $auraColor,
            ]);
        });
    }

    public function equip(User $student, SchoolClass $class, int $enrollmentPetId): Enrollment
    {
        return DB::transaction(function () use ($student, $class, $enrollmentPetId) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'pets' => 'Você não está matriculado nesta turma.',
                ]);
            }

            $owned = EnrollmentPet::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereKey($enrollmentPetId)
                ->lockForUpdate()
                ->first();

            if (! $owned) {
                throw ValidationException::withMessages([
                    'enrollment_pet_id' => 'Você não possui este mascote.',
                ]);
            }

            if (PetListing::query()->where('enrollment_pet_id', $owned->id)->exists()) {
                throw ValidationException::withMessages([
                    'enrollment_pet_id' => 'Retire o anúncio antes de equipar este mascote.',
                ]);
            }

            $enrollment->equipped_pet_id = $owned->id;
            $enrollment->save();

            return $enrollment;
        });
    }

    public function unequip(User $student, SchoolClass $class): Enrollment
    {
        return DB::transaction(function () use ($student, $class) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'pets' => 'Você não está matriculado nesta turma.',
                ]);
            }

            $enrollment->equipped_pet_id = null;
            $enrollment->save();

            return $enrollment;
        });
    }

    public function listForSale(User $student, SchoolClass $class, int $enrollmentPetId, int $price): PetListing
    {
        return DB::transaction(function () use ($student, $class, $enrollmentPetId, $price) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'pets' => 'Você não está matriculado nesta turma.',
                ]);
            }

            $owned = EnrollmentPet::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereKey($enrollmentPetId)
                ->lockForUpdate()
                ->first();

            if (! $owned) {
                throw ValidationException::withMessages([
                    'enrollment_pet_id' => 'Você só pode vender um mascote que já possui.',
                ]);
            }

            if ((int) $enrollment->equipped_pet_id === (int) $owned->id) {
                $enrollment->equipped_pet_id = null;
                $enrollment->save();
            }

            $existing = PetListing::query()
                ->where('enrollment_pet_id', $owned->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->price = $price;
                $existing->save();

                return $existing;
            }

            return PetListing::query()->create([
                'class_id' => $class->id,
                'enrollment_id' => $enrollment->id,
                'enrollment_pet_id' => $owned->id,
                'price' => $price,
            ]);
        });
    }

    public function cancelListing(User $student, SchoolClass $class, int $enrollmentPetId): void
    {
        $enrollment = $student->enrollmentIn($class);

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'pets' => 'Você não está matriculado nesta turma.',
            ]);
        }

        $deleted = PetListing::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('enrollment_pet_id', $enrollmentPetId)
            ->delete();

        if ($deleted === 0) {
            throw ValidationException::withMessages([
                'enrollment_pet_id' => 'Este mascote não está à venda.',
            ]);
        }
    }

    public function buyListing(User $buyer, SchoolClass $class, PetListing $listing): EnrollmentPet
    {
        if ((int) $listing->class_id !== (int) $class->id) {
            throw ValidationException::withMessages([
                'listing' => 'Este anúncio não pertence a esta turma.',
            ]);
        }

        return DB::transaction(function () use ($buyer, $class, $listing) {
            $lockedListing = PetListing::query()
                ->whereKey($listing->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedListing) {
                throw ValidationException::withMessages([
                    'listing' => 'Este anúncio não está mais disponível.',
                ]);
            }

            $sellerEnrollment = Enrollment::query()
                ->whereKey($lockedListing->enrollment_id)
                ->lockForUpdate()
                ->first();

            $buyerEnrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $buyer->id)
                ->lockForUpdate()
                ->first();

            if (! $sellerEnrollment || ! $buyerEnrollment) {
                throw ValidationException::withMessages([
                    'pets' => 'A venda não pôde ser concluída.',
                ]);
            }

            if ((int) $sellerEnrollment->id === (int) $buyerEnrollment->id) {
                throw ValidationException::withMessages([
                    'listing' => 'Você não pode comprar o próprio anúncio.',
                ]);
            }

            $owned = EnrollmentPet::query()
                ->whereKey($lockedListing->enrollment_pet_id)
                ->lockForUpdate()
                ->first();

            if (! $owned || (int) $owned->enrollment_id !== (int) $sellerEnrollment->id) {
                $lockedListing->delete();

                throw ValidationException::withMessages([
                    'listing' => 'O vendedor não possui mais este mascote.',
                ]);
            }

            if (EnrollmentPet::query()
                ->where('enrollment_id', $buyerEnrollment->id)
                ->where('pet_id', $owned->pet_id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'listing' => 'Você já possui este mascote.',
                ]);
            }

            $price = (int) $lockedListing->price;

            if ((int) $buyerEnrollment->relics < $price) {
                throw ValidationException::withMessages([
                    'listing' => GameCurrency::label('relics').' insuficientes para esta negociação.',
                ]);
            }

            $buyerEnrollment->relics = (int) $buyerEnrollment->relics - $price;
            $buyerEnrollment->save();

            $sellerEnrollment->relics = (int) $sellerEnrollment->relics + $price;
            if ((int) $sellerEnrollment->equipped_pet_id === (int) $owned->id) {
                $sellerEnrollment->equipped_pet_id = null;
            }
            $sellerEnrollment->save();

            $owned->enrollment_id = $buyerEnrollment->id;
            $owned->save();

            $lockedListing->delete();

            return $owned->fresh(['pet']);
        });
    }

    /**
     * @return array{bonus: float, name: ?string, aura_color: ?string, sprite_key: ?string, gif_url: ?string}
     */
    public function equippedCombatBonus(?Enrollment $enrollment): array
    {
        if (! $enrollment?->equipped_pet_id) {
            return [
                'bonus' => 0.0,
                'name' => null,
                'aura_color' => null,
                'sprite_key' => null,
                'gif_url' => null,
            ];
        }

        $owned = $enrollment->relationLoaded('equippedPet')
            ? $enrollment->equippedPet
            : $enrollment->equippedPet()->with('pet')->first();

        $pet = $owned?->pet;

        if (! $owned || ! $pet) {
            return [
                'bonus' => 0.0,
                'name' => null,
                'aura_color' => null,
                'sprite_key' => null,
                'gif_url' => null,
            ];
        }

        return [
            'bonus' => round((float) $pet->combat_bonus, 4),
            'name' => $owned->custom_name,
            'aura_color' => $owned->aura_color,
            'sprite_key' => $pet->spriteKey(),
            'gif_url' => $pet->gifUrl(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ownedPetsForStudent(User $student, SchoolClass $class): array
    {
        $enrollment = $student->enrollmentIn($class);
        if (! $enrollment) {
            return [];
        }

        $enrollment->loadMissing(['pets.pet']);
        $equippedId = (int) ($enrollment->equipped_pet_id ?? 0);

        return $enrollment->pets->map(function (EnrollmentPet $owned) use ($equippedId) {
            return $owned->toInventoryArray(equipped: (int) $owned->id === $equippedId);
        })->values()->all();
    }

    /**
     * @return array{id: int, custom_name: string, aura_color: string, sprite_key: string, gif_url: ?string, combat_bonus_percent: float, species_name: string}|null
     */
    public function equippedPetForStudent(User $student, SchoolClass $class): ?array
    {
        $enrollment = $student->enrollmentIn($class);
        if (! $enrollment?->equipped_pet_id) {
            return null;
        }

        $owned = $enrollment->equippedPet()->with('pet')->first();
        if (! $owned?->pet) {
            return null;
        }

        $row = $owned->toInventoryArray(equipped: true);

        return [
            'id' => $row['id'],
            'custom_name' => $row['custom_name'],
            'aura_color' => $row['aura_color'],
            'sprite_key' => $row['sprite_key'],
            'gif_url' => $row['gif_url'],
            'combat_bonus_percent' => $row['combat_bonus_percent'],
            'species_name' => $row['species_name'],
        ];
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @return array<int, array{stock_remaining: int, owned_copies: int, listings_count: int}>
     */
    public function overviewForClasses(Collection $classes): array
    {
        $overview = [];

        foreach ($classes as $class) {
            $this->seedForClass($class);
            $overview[$class->id] = [
                'stock_remaining' => (int) Pet::query()->where('class_id', $class->id)->sum('stock'),
                'owned_copies' => EnrollmentPet::query()
                    ->whereHas('enrollment', fn ($q) => $q->where('class_id', $class->id))
                    ->count(),
                'listings_count' => PetListing::query()->where('class_id', $class->id)->count(),
            ];
        }

        return $overview;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertHasPrice(array $data): void
    {
        $total = (int) ($data['price_relics'] ?? 0)
            + (int) ($data['price_seals'] ?? 0)
            + (int) ($data['price_auras'] ?? 0);

        if ($total <= 0) {
            throw ValidationException::withMessages([
                'price_relics' => 'Informe pelo menos um preço maior que zero.',
            ]);
        }
    }

    private function storeGif(UploadedFile $gif, int $classId): string
    {
        $extension = strtolower((string) ($gif->getClientOriginalExtension() ?: $gif->extension() ?: 'gif'));

        if (! in_array($extension, ['gif', 'webp', 'png', 'jpg', 'jpeg'], true)) {
            $extension = 'gif';
        }

        $path = $gif->storeAs(
            'pets/'.$classId,
            Str::random(40).'.'.$extension,
            'public'
        );

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'gif' => 'Não foi possível salvar o arquivo do mascote. Tente novamente.',
            ]);
        }

        return $path;
    }

    private function debitPrices(User $student, SchoolClass $class, Enrollment $enrollment, Pet $pet): void
    {
        $relics = (int) $pet->price_relics;
        $seals = (int) $pet->price_seals;
        $auras = (int) $pet->price_auras;

        if ($relics > 0 && (int) $enrollment->relics < $relics) {
            throw ValidationException::withMessages([
                'pet_id' => GameCurrency::label('relics').' insuficientes para comprar este mascote.',
            ]);
        }

        if ($seals > 0 && (int) $enrollment->seals < $seals) {
            throw ValidationException::withMessages([
                'pet_id' => GameCurrency::label('seals').' insuficientes para comprar este mascote.',
            ]);
        }

        if ($auras > 0) {
            $area = $class->area;
            if (! $area) {
                throw ValidationException::withMessages([
                    'pet_id' => 'Esta turma não pertence a um reino para gastar Aura.',
                ]);
            }

            $areaBalance = AreaBalance::query()
                ->where('area_id', $area->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            $balance = (int) ($areaBalance?->auras ?? 0);
            if ($balance < $auras) {
                throw ValidationException::withMessages([
                    'pet_id' => GameCurrency::label('auras').' insuficientes para comprar este mascote.',
                ]);
            }

            if (! $areaBalance) {
                $areaBalance = AreaBalance::query()->create([
                    'area_id' => $area->id,
                    'student_id' => $student->id,
                    'auras' => 0,
                ]);
                $areaBalance = AreaBalance::query()->whereKey($areaBalance->id)->lockForUpdate()->firstOrFail();
            }

            $areaBalance->auras = $balance - $auras;
            $areaBalance->save();
        }

        if ($relics > 0) {
            $enrollment->relics = (int) $enrollment->relics - $relics;
        }
        if ($seals > 0) {
            $enrollment->seals = (int) $enrollment->seals - $seals;
        }
        $enrollment->save();
    }
}
