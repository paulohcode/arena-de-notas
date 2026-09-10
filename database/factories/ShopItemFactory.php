<?php

namespace Database\Factories;

use App\Models\ShopItem;
use App\Support\CosmeticCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopItem>
 */
class ShopItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'class_id' => null,
            'area_id' => null,
            'item_key' => 'custom_accessory_'.Str::slug($name, '_').'_'.fake()->unique()->numerify('###'),
            'slot' => CosmeticCatalog::SLOT_ACCESSORY,
            'name' => Str::title($name),
            'price' => 40,
            'currency' => CosmeticCatalog::CURRENCY_RELICS,
            'rarity' => 'common',
            'icon' => '🛡️',
            'css' => null,
            'label' => null,
            'combat_bonus' => null,
            'prize_only' => false,
        ];
    }

    public function forClass(int $classId): static
    {
        return $this->state(fn (array $attributes): array => [
            'class_id' => $classId,
        ]);
    }

    public function prizeOnly(): static
    {
        return $this->state(fn (): array => [
            'prize_only' => true,
            'price' => 0,
        ]);
    }
}
