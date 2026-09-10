<?php

namespace Database\Factories;

use App\Models\GameEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEvent>
 */
class GameEventFactory extends Factory
{
    protected $model = GameEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Evento '.fake()->unique()->words(2, true),
            'kind' => GameEvent::KIND_CLASS,
            'mode' => GameEvent::MODE_WINDOW,
            'status' => GameEvent::STATUS_LIVE,
            'class_id' => null,
            'area_id' => null,
            'activity_id' => null,
            'created_by' => User::factory(),
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
            'question_seconds' => 30,
            'relics_per_correct' => 0,
            'seals_per_correct' => 0,
            'auras_per_correct' => 0,
        ];
    }

    public function activity(): static
    {
        return $this->state(fn (): array => [
            'kind' => GameEvent::KIND_ACTIVITY,
        ]);
    }

    public function realm(): static
    {
        return $this->state(fn (): array => [
            'kind' => GameEvent::KIND_REALM,
            'class_id' => null,
        ]);
    }

    public function live(): static
    {
        return $this->state(fn (): array => [
            'mode' => GameEvent::MODE_LIVE,
            'status' => GameEvent::STATUS_SCHEDULED,
            'ends_at' => null,
        ]);
    }
}
