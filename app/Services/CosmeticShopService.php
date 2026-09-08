<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\EnrollmentCosmetic;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CosmeticCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CosmeticShopService
{
    /**
     * @return array{
     *     enrollment: Enrollment,
     *     owned: list<string>,
     *     loadout: array{frame: ?string, accessory: ?string, title: ?string, aura: ?string},
     *     catalog: array<string, list<array{key: string, slot: string, name: string, price: int, rarity: string, rarity_label: string, icon?: string, css?: string, label?: string, owned: bool, equipped: bool}>>
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

        $owned = $enrollment->cosmetics()->pluck('item_key')->all();
        $ownedSet = array_fill_keys($owned, true);
        $loadout = $enrollment->cosmeticLoadout();

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
                    'rarity' => $item['rarity'],
                    'rarity_label' => CosmeticCatalog::rarityLabel($item['rarity']),
                    'icon' => $item['icon'] ?? null,
                    'css' => $item['css'] ?? null,
                    'label' => $item['label'] ?? null,
                    'owned' => isset($ownedSet[$key]),
                    'equipped' => $equippedKey === $key,
                ];
            }
        }

        return [
            'enrollment' => $enrollment,
            'owned' => $owned,
            'loadout' => $loadout,
            'catalog' => $catalog,
        ];
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

            if ((int) $enrollment->relics < $price) {
                throw ValidationException::withMessages([
                    'item' => 'Relíquias insuficientes para comprar este item.',
                ]);
            }

            $enrollment->relics = (int) $enrollment->relics - $price;
            $enrollment->save();

            EnrollmentCosmetic::query()->create([
                'enrollment_id' => $enrollment->id,
                'item_key' => $itemKey,
            ]);

            return $enrollment->fresh(['cosmetics']);
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
}
