<?php

namespace Tests\Feature;

use App\Models\Duel;
use App\Models\User;
use App\Services\RankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingHeadToHeadTiebreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_to_head_arena_wins_break_average_and_xp_ties(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $ana = User::factory()->create(['role' => 'student', 'name' => 'Ana Souza']);
        $bruno = User::factory()->create(['role' => 'student', 'name' => 'Bruno Lima']);

        $class->students()->attach($ana->id, ['ranking_visible' => true, 'xp' => 10]);
        $class->students()->attach($bruno->id, ['ranking_visible' => true, 'xp' => 10]);

        // Bruno venceu Ana 2x no confronto direto (mesmo empatando em média e XP).
        $this->createResolvedDuel($class->id, $bruno->id, $ana->id, $bruno->id);
        $this->createResolvedDuel($class->id, $ana->id, $bruno->id, $bruno->id);

        $players = app(RankingService::class)->players($class);

        $this->assertSame($bruno->id, $players[0]['student']->id);
        $this->assertSame(1, $players[0]['position']);
        $this->assertSame($ana->id, $players[1]['student']->id);
        $this->assertSame(2, $players[1]['position']);
    }

    public function test_falls_back_to_name_when_head_to_head_is_tied(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $ana = User::factory()->create(['role' => 'student', 'name' => 'Ana Souza']);
        $bruno = User::factory()->create(['role' => 'student', 'name' => 'Bruno Lima']);

        $class->students()->attach($ana->id, ['ranking_visible' => true, 'xp' => 5]);
        $class->students()->attach($bruno->id, ['ranking_visible' => true, 'xp' => 5]);

        $this->createResolvedDuel($class->id, $bruno->id, $ana->id, $bruno->id);
        $this->createResolvedDuel($class->id, $ana->id, $bruno->id, $ana->id);

        $players = app(RankingService::class)->players($class);

        $this->assertSame($ana->id, $players[0]['student']->id);
        $this->assertSame($bruno->id, $players[1]['student']->id);
    }

    public function test_xp_still_outranks_head_to_head_when_averages_match(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $ana = User::factory()->create(['role' => 'student', 'name' => 'Ana Souza']);
        $bruno = User::factory()->create(['role' => 'student', 'name' => 'Bruno Lima']);

        $class->students()->attach($ana->id, ['ranking_visible' => true, 'xp' => 50]);
        $class->students()->attach($bruno->id, ['ranking_visible' => true, 'xp' => 10]);

        $this->createResolvedDuel($class->id, $bruno->id, $ana->id, $bruno->id);
        $this->createResolvedDuel($class->id, $ana->id, $bruno->id, $bruno->id);

        $players = app(RankingService::class)->players($class);

        $this->assertSame($ana->id, $players[0]['student']->id);
        $this->assertSame($bruno->id, $players[1]['student']->id);
    }

    private function createResolvedDuel(int $classId, int $challengerId, int $opponentId, int $winnerId): void
    {
        Duel::query()->create([
            'class_id' => $classId,
            'challenger_id' => $challengerId,
            'opponent_id' => $opponentId,
            'status' => Duel::STATUS_RESOLVED,
            'seed' => random_int(1, 999_999),
            'log' => ['turns' => [], 'fighters' => [], 'winner_id' => $winnerId],
            'winner_id' => $winnerId,
            'glory_winner' => Duel::GLORY_WIN,
            'glory_loser' => Duel::GLORY_LOSS,
            'resolved_at' => now(),
        ]);
    }
}
