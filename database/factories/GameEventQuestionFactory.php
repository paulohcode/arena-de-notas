<?php

namespace Database\Factories;

use App\Models\GameEvent;
use App\Models\GameEventQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEventQuestion>
 */
class GameEventQuestionFactory extends Factory
{
    protected $model = GameEventQuestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_event_id' => GameEvent::factory(),
            'prompt' => fake()->sentence().'?',
            'type' => GameEventQuestion::TYPE_MULTIPLE_CHOICE,
            'options' => ['A', 'B', 'C', 'D'],
            'correct_index' => 0,
            'position' => 0,
        ];
    }

    public function trueFalse(bool $correctIsTrue = true): static
    {
        return $this->state(fn (): array => [
            'type' => GameEventQuestion::TYPE_TRUE_FALSE,
            'options' => ['Verdadeiro', 'Falso'],
            'correct_index' => $correctIsTrue ? 0 : 1,
        ]);
    }
}
