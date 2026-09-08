<?php

namespace App\Services;

use App\Models\ClassCosmeticStock;
use App\Models\CosmeticListing;
use App\Models\Enrollment;
use App\Models\EnrollmentCosmetic;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CosmeticCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CosmeticShopService
{
    public const DEFAULT_STOCK = 1;

    /**
     * @return array{
     *     enrollment: Enrollment,
     *     owned: list<string>,
     *     loadout: array{frame: ?string, accessory: ?string, title: ?string, aura: ?string},
     *     listings: list<array{id: int, item_key: string, name: string, slot: string, rarity: string, rarity_label: string, icon: ?string, css: ?string, label: ?string, price: int, seller_id: int, seller_name: string, already_owned: bool}>,
     *     catalog: array<string, list<array{key: string, slot: string, name: string, price: int, rarity: string, rarity_label: string, icon?: string, css?: string, label?: string, owned: bool, equipped: bool, stock: int, listed_price: ?int}>>
     * }
     */
    public function shopData(User $student, SchoolClass $class): array
    {
        $enrollment = $student->enrollmentIn($class);

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'shop' => 'Você não está matriculado nesta turma.',
            ]);
        }

        $this->seedDefaultStock($class);

        $owned = $enrollment->cosmetics()->pluck('item_key')->all();
        $ownedSet = array_fill_keys($owned, true);
        $loadout = $enrollment->cosmeticLoadout();

        $stockByKey = ClassCosmeticStock::query()
            ->where('class_id', $class->id)
            ->pluck('quantity', 'item_key');

        $ownListings = CosmeticListing::query()
            ->where('enrollment_id', $enrollment->id)
            ->pluck('price', 'item_key');

        $peerListings = CosmeticListing::query()
            ->with(['enrollment.student'])
            ->where('class_id', $class->id)
            ->where('enrollment_id', '!=', $enrollment->id)
            ->orderBy('price')
            ->orderBy('id')
            ->get();

        $catalog = [];

        foreach (CosmeticCatalog::SLOTS as $slot => $label) {
            $catalog[$slot] = [];

            foreach (CosmeticCatalog::keysForSlot($slot) as $key) {
                $item = CosmeticCatalog::item($key);
                if (! $item) {
                    continue;
                }

                $column = CosmeticCatalog::slotColumn($slot);
                $equippedKey = $column ? ($enrollment->{$column} ?? null) : null;

                $catalog[$slot][] = [
                    'key' => $key,
                    'slot' => $item['slot'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'currency' => CosmeticCatalog::currency($key),
                    'currency_label' => CosmeticCatalog::currencyLabel(CosmeticCatalog::currency($key)),
                    'rarity' => $item['rarity'],
                    'rarity_label' => CosmeticCatalog::rarityLabel($item['rarity']),
                    'icon' => CosmeticCatalog::icon($key),
                    'css' => $item['css'] ?? null,
                    'label' => $item['label'] ?? null,
                    'owned' => isset($ownedSet[$key]),
                    'equipped' => $equippedKey === $key,
                    'stock' => (int) ($stockByKey[$key] ?? 0),
                    'listed_price' => isset($ownListings[$key]) ? (int) $ownListings[$key] : null,
                    'tradable' => ! CosmeticCatalog::usesSeals($key),
                ];
            }
        }

        $listings = [];
        foreach ($peerListings as $listing) {
            $item = CosmeticCatalog::item($listing->item_key);
            if (! $item || CosmeticCatalog::usesSeals($listing->item_key)) {
                continue;
            }

            $seller = $listing->enrollment?->student;
            $listings[] = [
                'id' => $listing->id,
                'item_key' => $listing->item_key,
                'name' => $item['name'],
                'slot' => $item['slot'],
                'rarity' => $item['rarity'],
                'rarity_label' => CosmeticCatalog::rarityLabel($item['rarity']),
                'icon' => CosmeticCatalog::icon($listing->item_key),
                'css' => $item['css'] ?? null,
                'label' => $item['label'] ?? null,
                'price' => (int) $listing->price,
                'seller_id' => (int) $listing->enrollment->student_id,
                'seller_name' => $seller?->arenaName() ?: ($seller?->name ?? 'Colega'),
                'already_owned' => isset($ownedSet[$listing->item_key]),
            ];
        }

        return [
            'enrollment' => $enrollment,
            'owned' => $owned,
            'loadout' => $loadout,
            'listings' => $listings,
            'catalog' => $catalog,
        ];
    }

    /**
     * @return array{
     *     catalog: array<string, list<array{key: string, slot: string, name: string, price: int, rarity: string, rarity_label: string, icon?: string, css?: string, label?: string, stock: int, owners: list<array{name: string, character: ?string, equipped: bool}>, listings: list<array{seller: string, price: int}>}>>
     * }
     */
    public function inventory(SchoolClass $class): array
    {
        $this->seedDefaultStock($class);

        $class->load([
            'enrollments.student',
            'enrollments.cosmetics',
            'cosmeticStocks',
            'cosmeticListings.enrollment.student',
        ]);

        $stockByKey = $class->cosmeticStocks->pluck('quantity', 'item_key');

        $ownersByKey = [];
        foreach ($class->enrollments as $enrollment) {
            $loadout = $enrollment->cosmeticLoadout();
            $student = $enrollment->student;
            $displayName = $student?->name ?? 'Aluno';
            $character = $student?->arenaName();

            foreach ($enrollment->cosmetics as $cosmetic) {
                $ownersByKey[$cosmetic->item_key][] = [
                    'name' => $displayName,
                    'character' => $character,
                    'equipped' => in_array($cosmetic->item_key, $loadout, true),
                ];
            }
        }

        $listingsByKey = [];
        foreach ($class->cosmeticListings as $listing) {
            $seller = $listing->enrollment?->student;
            $listingsByKey[$listing->item_key][] = [
                'seller' => $seller?->arenaName() ?: ($seller?->name ?? 'Aluno'),
                'price' => (int) $listing->price,
            ];
        }

        $catalog = [];

        foreach (CosmeticCatalog::SLOTS as $slot => $label) {
            $catalog[$slot] = [];

            foreach (CosmeticCatalog::keysForSlot($slot) as $key) {
                $item = CosmeticCatalog::item($key);
                if (! $item) {
                    continue;
                }

                $catalog[$slot][] = [
                    'key' => $key,
                    'slot' => $item['slot'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'currency' => CosmeticCatalog::currency($key),
                    'currency_label' => CosmeticCatalog::currencyLabel(CosmeticCatalog::currency($key)),
                    'rarity' => $item['rarity'],
                    'rarity_label' => CosmeticCatalog::rarityLabel($item['rarity']),
                    'icon' => CosmeticCatalog::icon($key),
                    'css' => $item['css'] ?? null,
                    'label' => $item['label'] ?? null,
                    'stock' => (int) ($stockByKey[$key] ?? 0),
                    'owners' => $ownersByKey[$key] ?? [],
                    'listings' => CosmeticCatalog::usesSeals($key) ? [] : ($listingsByKey[$key] ?? []),
                    'tradable' => ! CosmeticCatalog::usesSeals($key),
                ];
            }
        }

        return ['catalog' => $catalog];
    }

    /**
     * @param  Collection<int, SchoolClass>  $classes
     * @return Collection<int, array{stock_remaining: int, owned_copies: int, listings_count: int}>
     */
    public function overviewForClasses(Collection $classes): Collection
    {
        foreach ($classes as $class) {
            $this->seedDefaultStock($class);
        }

        $classIds = $classes->pluck('id');

        $stock = ClassCosmeticStock::query()
            ->whereIn('class_id', $classIds)
            ->selectRaw('class_id, SUM(quantity) as stock_remaining')
            ->groupBy('class_id')
            ->pluck('stock_remaining', 'class_id');

        $owned = EnrollmentCosmetic::query()
            ->join('enrollments', 'enrollments.id', '=', 'enrollment_cosmetics.enrollment_id')
            ->whereIn('enrollments.class_id', $classIds)
            ->selectRaw('enrollments.class_id as class_id, COUNT(*) as owned_copies')
            ->groupBy('enrollments.class_id')
            ->pluck('owned_copies', 'class_id');

        $listings = CosmeticListing::query()
            ->whereIn('class_id', $classIds)
            ->selectRaw('class_id, COUNT(*) as listings_count')
            ->groupBy('class_id')
            ->pluck('listings_count', 'class_id');

        return $classes->mapWithKeys(fn (SchoolClass $class) => [
            $class->id => [
                'stock_remaining' => (int) ($stock[$class->id] ?? 0),
                'owned_copies' => (int) ($owned[$class->id] ?? 0),
                'listings_count' => (int) ($listings[$class->id] ?? 0),
            ],
        ]);
    }

    public function seedDefaultStock(SchoolClass $class): void
    {
        $existing = ClassCosmeticStock::query()
            ->where('class_id', $class->id)
            ->pluck('item_key')
            ->all();

        $missing = array_diff(array_keys(CosmeticCatalog::ITEMS), $existing);

        if ($missing === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($missing as $key) {
            $rows[] = [
                'class_id' => $class->id,
                'item_key' => $key,
                'quantity' => self::DEFAULT_STOCK,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ClassCosmeticStock::query()->insert($rows);
    }

    public function restock(SchoolClass $class, string $itemKey, int $quantity): ClassCosmeticStock
    {
        if (! CosmeticCatalog::has($itemKey)) {
            throw ValidationException::withMessages([
                'item' => 'Este item não existe na loja.',
            ]);
        }

        $this->seedDefaultStock($class);

        $stock = ClassCosmeticStock::query()->updateOrCreate(
            [
                'class_id' => $class->id,
                'item_key' => $itemKey,
            ],
            ['quantity' => $quantity],
        );

        return $stock;
    }

    public function purchase(User $student, SchoolClass $class, string $itemKey): Enrollment
    {
        if (! CosmeticCatalog::has($itemKey)) {
            throw ValidationException::withMessages([
                'item' => 'Este item não existe na loja.',
            ]);
        }

        $item = CosmeticCatalog::item($itemKey);

        return DB::transaction(function () use ($student, $class, $itemKey, $item) {
            $this->seedDefaultStock($class);

            $stock = ClassCosmeticStock::query()
                ->where('class_id', $class->id)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            if (! $stock || (int) $stock->quantity < 1) {
                throw ValidationException::withMessages([
                    'item' => 'Este item esgotou na loja. Negocie com quem já possui.',
                ]);
            }

            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'shop' => 'Você não está matriculado nesta turma.',
                ]);
            }

            if ($enrollment->ownsCosmetic($itemKey)) {
                throw ValidationException::withMessages([
                    'item' => 'Você já possui este item.',
                ]);
            }

            $price = (int) $item['price'];
            $currency = CosmeticCatalog::currency($itemKey);
            $balance = $currency === CosmeticCatalog::CURRENCY_SEALS
                ? (int) $enrollment->seals
                : (int) $enrollment->relics;

            if ($balance < $price) {
                $label = CosmeticCatalog::currencyLabel($currency);
                throw ValidationException::withMessages([
                    'item' => "{$label} insuficientes para comprar este item.",
                ]);
            }

            if ($currency === CosmeticCatalog::CURRENCY_SEALS) {
                $enrollment->seals = $balance - $price;
            } else {
                $enrollment->relics = $balance - $price;
            }
            $enrollment->save();

            $stock->quantity = (int) $stock->quantity - 1;
            $stock->save();

            EnrollmentCosmetic::query()->create([
                'enrollment_id' => $enrollment->id,
                'item_key' => $itemKey,
            ]);

            return $enrollment->fresh(['cosmetics']);
        });
    }

    public function listForSale(User $student, SchoolClass $class, string $itemKey, int $price): CosmeticListing
    {
        if (! CosmeticCatalog::has($itemKey)) {
            throw ValidationException::withMessages([
                'item' => 'Este item não existe.',
            ]);
        }

        if (CosmeticCatalog::usesSeals($itemKey)) {
            throw ValidationException::withMessages([
                'item' => 'Itens de presença não podem ser anunciados no mercado.',
            ]);
        }

        return DB::transaction(function () use ($student, $class, $itemKey, $price) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'shop' => 'Você não está matriculado nesta turma.',
                ]);
            }

            if (! $enrollment->ownsCosmetic($itemKey)) {
                throw ValidationException::withMessages([
                    'item' => 'Você só pode vender um item que já possui.',
                ]);
            }

            $existing = CosmeticListing::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->price = $price;
                $existing->save();

                return $existing;
            }

            return CosmeticListing::query()->create([
                'class_id' => $class->id,
                'enrollment_id' => $enrollment->id,
                'item_key' => $itemKey,
                'price' => $price,
            ]);
        });
    }

    public function cancelListing(User $student, SchoolClass $class, string $itemKey): void
    {
        $enrollment = $student->enrollmentIn($class);

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'shop' => 'Você não está matriculado nesta turma.',
            ]);
        }

        $deleted = CosmeticListing::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('item_key', $itemKey)
            ->delete();

        if ($deleted === 0) {
            throw ValidationException::withMessages([
                'item' => 'Este item não está à venda.',
            ]);
        }
    }

    public function buyListing(User $buyer, SchoolClass $class, CosmeticListing $listing): Enrollment
    {
        if ((int) $listing->class_id !== (int) $class->id) {
            throw ValidationException::withMessages([
                'listing' => 'Este anúncio não pertence a esta turma.',
            ]);
        }

        return DB::transaction(function () use ($buyer, $class, $listing) {
            $lockedListing = CosmeticListing::query()
                ->where('id', $listing->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedListing) {
                throw ValidationException::withMessages([
                    'listing' => 'Este anúncio não está mais disponível.',
                ]);
            }

            $sellerEnrollment = Enrollment::query()
                ->where('id', $lockedListing->enrollment_id)
                ->lockForUpdate()
                ->first();

            $buyerEnrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $buyer->id)
                ->lockForUpdate()
                ->first();

            if (! $sellerEnrollment || ! $buyerEnrollment) {
                throw ValidationException::withMessages([
                    'shop' => 'A venda não pôde ser concluída.',
                ]);
            }

            if ((int) $sellerEnrollment->id === (int) $buyerEnrollment->id) {
                throw ValidationException::withMessages([
                    'listing' => 'Você não pode comprar o próprio anúncio.',
                ]);
            }

            $itemKey = $lockedListing->item_key;
            $price = (int) $lockedListing->price;

            if (CosmeticCatalog::usesSeals($itemKey)) {
                throw ValidationException::withMessages([
                    'listing' => 'Itens de presença não podem ser negociados no mercado.',
                ]);
            }

            $owned = EnrollmentCosmetic::query()
                ->where('enrollment_id', $sellerEnrollment->id)
                ->where('item_key', $itemKey)
                ->lockForUpdate()
                ->first();

            if (! $owned) {
                $lockedListing->delete();

                throw ValidationException::withMessages([
                    'listing' => 'O vendedor não possui mais este item.',
                ]);
            }

            if ($buyerEnrollment->ownsCosmetic($itemKey)) {
                throw ValidationException::withMessages([
                    'listing' => 'Você já possui este item.',
                ]);
            }

            if ((int) $buyerEnrollment->relics < $price) {
                throw ValidationException::withMessages([
                    'listing' => 'Relíquias insuficientes para esta negociação.',
                ]);
            }

            $buyerEnrollment->relics = (int) $buyerEnrollment->relics - $price;
            $buyerEnrollment->save();

            $sellerEnrollment->relics = (int) $sellerEnrollment->relics + $price;
            $this->clearEquippedSlot($sellerEnrollment, $itemKey);
            $sellerEnrollment->save();

            $owned->enrollment_id = $buyerEnrollment->id;
            $owned->save();

            $lockedListing->delete();

            return $buyerEnrollment->fresh(['cosmetics']);
        });
    }

    public function equip(User $student, SchoolClass $class, string $itemKey): Enrollment
    {
        if (! CosmeticCatalog::has($itemKey)) {
            throw ValidationException::withMessages([
                'item' => 'Este item não existe.',
            ]);
        }

        $item = CosmeticCatalog::item($itemKey);
        $column = CosmeticCatalog::slotColumn($item['slot']);

        if (! $column) {
            throw ValidationException::withMessages([
                'item' => 'Slot de cosmético inválido.',
            ]);
        }

        return DB::transaction(function () use ($student, $class, $itemKey, $column) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'shop' => 'Você não está matriculado nesta turma.',
                ]);
            }

            if (! $enrollment->ownsCosmetic($itemKey)) {
                throw ValidationException::withMessages([
                    'item' => 'Compre o item antes de equipar.',
                ]);
            }

            $enrollment->{$column} = $itemKey;
            $enrollment->save();

            return $enrollment->fresh();
        });
    }

    public function unequip(User $student, SchoolClass $class, string $slot): Enrollment
    {
        $column = CosmeticCatalog::slotColumn($slot);

        if (! $column) {
            throw ValidationException::withMessages([
                'slot' => 'Slot inválido.',
            ]);
        }

        return DB::transaction(function () use ($student, $class, $column) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'shop' => 'Você não está matriculado nesta turma.',
                ]);
            }

            $enrollment->{$column} = null;
            $enrollment->save();

            return $enrollment->fresh();
        });
    }

    private function clearEquippedSlot(Enrollment $enrollment, string $itemKey): void
    {
        $item = CosmeticCatalog::item($itemKey);
        $column = $item ? CosmeticCatalog::slotColumn($item['slot']) : null;

        if ($column && $enrollment->{$column} === $itemKey) {
            $enrollment->{$column} = null;
        }
    }
}
