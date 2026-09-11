<?php

namespace Tests\Feature;

use App\Models\Duel;
use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TeamBattle;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Services\ChallengeExpiryService;
use App\Services\GameEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ChallengeExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tick_expires_stale_pending_challenges_and_records_heartbeat(): void
    {
        Notification::fake();

        $this->travelTo('2026-09-09 12:00:00');

        [$class, $challenger, $opponent] = $this->readyPair();
        $duel = Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'status' => Duel::STATUS_PENDING,
        ]);
        $challenger->enrollmentIn($class)->update(['glory' => 4, 'relics' => 4]);

        $alpha = Team::query()->create(['class_id' => $class->id, 'name' => 'Alpha', 'emblem' => 'shield']);
        $beta = Team::query()->create(['class_id' => $class->id, 'name' => 'Beta', 'emblem' => 'sword']);
        $alpha->members()->attach($challenger->id);
        $beta->members()->attach($opponent->id);
        $battle = TeamBattle::query()->create([
            'class_id' => $class->id,
            'challenger_team_id' => $alpha->id,
            'opponent_team_id' => $beta->id,
            'challenger_id' => $challenger->id,
            'status' => TeamBattle::STATUS_PENDING,
        ]);

        $realmClass = $this->createClassForTeacher($class->teacher, [
            'name' => 'Outra turma',
            'arena_open' => true,
            'area' => $class->area,
        ]);
        $realmOpponent = $this->enrollStudent($realmClass, 'Carla Dias');
        $realmDuel = RealmDuel::query()->create([
            'area_id' => $class->area_id,
            'challenger_class_id' => $class->id,
            'opponent_class_id' => $realmClass->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $realmOpponent->id,
            'status' => RealmDuel::STATUS_PENDING,
        ]);

        $this->travelTo('2026-09-10 12:00:00');

        $this->artisan('game-events:tick')
            ->expectsOutputToContain('Desafios expirados: 3')
            ->assertSuccessful();

        $this->assertSame(Duel::STATUS_EXPIRED, $duel->fresh()->status);
        $this->assertSame(TeamBattle::STATUS_EXPIRED, $battle->fresh()->status);
        $this->assertSame(RealmDuel::STATUS_EXPIRED, $realmDuel->fresh()->status);
        $this->assertSame(4, (int) $challenger->enrollmentIn($class)->fresh()->glory);
        $this->assertSame(now()->timestamp, (int) Cache::get(GameEventService::LAST_TICK_CACHE_KEY));

        Notification::assertSentTo($challenger, GameAlert::class);
        Notification::assertSentTo($opponent, GameAlert::class);
        Notification::assertSentTo($realmOpponent, GameAlert::class);
    }

    public function test_recent_pending_challenges_stay_open(): void
    {
        $this->travelTo('2026-09-10 11:00:00');

        [$class, $challenger, $opponent] = $this->readyPair();
        $duel = Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'status' => Duel::STATUS_PENDING,
        ]);

        $this->travelTo('2026-09-10 12:00:00');

        $this->assertSame(0, app(ChallengeExpiryService::class)->expirePending());
        $this->assertSame(Duel::STATUS_PENDING, $duel->fresh()->status);
    }

    public function test_expired_duel_page_redirects_the_challenger_home(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair();
        $duel = Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'status' => Duel::STATUS_EXPIRED,
            'resolved_at' => now(),
        ]);

        $this->actingAs($challenger)
            ->get(route('student.arena.show', $duel))
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHas('success', 'Este desafio expirou.');
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: User}
     */
    private function readyPair(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $challenger = $this->enrollStudent($class, 'Ana Souza');
        $opponent = $this->enrollStudent($class, 'Bruno Lima');

        return [$class, $challenger, $opponent];
    }

    private function enrollStudent(SchoolClass $class, string $name): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
            'character_name' => 'Heroi '.$name,
            'character_avatar' => 'lobo',
            'character_approval_status' => 'approved',
        ]);

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'glory' => 0,
            'relics' => 0,
            'arena_wins' => 0,
            'arena_losses' => 0,
            'behavior_score' => 100,
        ]);

        return $student->fresh();
    }
}
