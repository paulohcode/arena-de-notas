<?php

namespace Database\Factories;

use App\Models\GameEventAnswer;
use App\Models\GameEventAttempt;
use App\Models\GameEventQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEventAnswer>
 */
class GameEventAnswerFactory extends Factory
{
    protected $model = GameEventAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_event_attempt_id' => GameEventAttempt::factory(),
            'game_event_question_id' => GameEventQuestion::factory(),
            'selected_index' => 0,
            'is_correct' => true,
            'elapsed_ms' => 1000,
        ];
    }
}
