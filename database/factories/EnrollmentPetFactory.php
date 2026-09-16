<?php

namespace Database\Factories;

use App\Models\EnrollmentPet;
use App\Support\PetCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentPet>
 */
class EnrollmentPetFactory extends Factory
{
    protected $model = EnrollmentPet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => 1,
            'pet_id' => 1,
            'custom_name' => fake()->firstName(),
            'aura_color' => fake()->randomElement(array_keys(PetCatalog::AURA_COLORS)),
        ];
    }
}
