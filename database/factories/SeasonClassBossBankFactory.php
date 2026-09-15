<?php

namespace Database\Factories;

use App\Models\SeasonClassBossBank;
use App\Support\BossArchetypeCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonClassBossBank>
 */
class SeasonClassBossBankFactory extends Factory
{
    protected $model = SeasonClassBossBank::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relics' => 0,
            'battles' => 0,
            'next_battle' => fake()->numberBetween(
                BossArchetypeCatalog::JACKPOT_BATTLE_MIN,
                BossArchetypeCatalog::JACKPOT_BATTLE_MAX,
            ),
            'payout_percent' => fake()->numberBetween(
                BossArchetypeCatalog::JACKPOT_PERCENT_MIN,
                BossArchetypeCatalog::JACKPOT_PERCENT_MAX,
            ),
        ];
    }
}
