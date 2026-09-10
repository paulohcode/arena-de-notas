<?php

namespace Database\Factories;

use App\Models\GameEvent;
use App\Models\GameEventAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameEventAttempt>
 */
class GameEventAttemptFactory extends Factory
{
    protected $model = GameEventAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_event_id' => GameEvent::factory(),
            'student_id' => User::factory(),
            'class_id' => 1,
            'correct_count' => 0,
            'correct_time_ms' => 0,
            'started_at' => now(),
        ];
    }
}
