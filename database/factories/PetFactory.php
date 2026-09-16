<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\SchoolClass;
use App\Support\PetCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $species = PetCatalog::SPECIES['owl_sage'];

        return [
            'class_id' => 1,
            'species_key' => null,
            'name' => 'Mascote '.fake()->unique()->numerify('###'),
            'description' => $species['description'],
            'rarity' => 'common',
            'sprite_key' => 'owl',
            'gif_path' => null,
            'price_relics' => 280,
            'price_seals' => 40,
            'price_auras' => 280,
            'combat_bonus' => 0.02,
            'stock' => PetCatalog::DEFAULT_STOCK,
            'active' => true,
        ];
    }

    public function forClass(SchoolClass|int $class): static
    {
        $classId = $class instanceof SchoolClass ? $class->id : $class;

        return $this->state(fn (): array => [
            'class_id' => $classId,
        ]);
    }

    public function species(string $key): static
    {
        $species = PetCatalog::SPECIES[$key];

        return $this->state(fn (): array => [
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
        ]);
    }
}
