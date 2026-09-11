<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TeamBattle;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Services\TeamBattleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArenaGuildBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_challenge_guild_while_arena_is_closed(): void
    {
        [$class, $challenger, $alpha, $beta] = $this->readyGuilds(arenaOpen: false);

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['arena']);
    }

    public function test_student_without_guild_cannot_challenge(): void
    {
        [$class, $challenger, $alpha, $beta] = $this->readyGuilds(arenaOpen: true);
        $loner = $this->enrollStudent($class, 'Solo Silva', approvedPersona: true);

        $this->actingAs($loner)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors(['opponent_team_id']);
    }

    public function test_cannot_challenge_own_guild(): void
    {
        [$class, $challenger, $alpha, $beta] = $this->readyGuilds(arenaOpen: true);

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $alpha->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors(['opponent_team_id']);
    }

    public function test_full_guild_battle_flow_awards_glory_without_changing_xp(): void
    {
        Notification::fake();

        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);

        $challengerEnrollment = $challenger->enrollmentIn($class);
        $challengerEnrollment->update(['xp' => 250, 'glory' => 0, 'relics' => 0]);
        $defenderEnrollment = $defender->enrollmentIn($class);
        $defenderEnrollment->update(['xp' => 100, 'glory' => 0, 'relics' => 0]);

        $this->actingAs($challenger)
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect(route('student.arena.guild.show', TeamBattle::query()->firstOrFail()))
            ->assertSessionHas('success');

        $battle = TeamBattle::query()->firstOrFail();
        $this->assertSame(TeamBattle::STATUS_PENDING, $battle->status);

        Notification::assertSentTo($defender, GameAlert::class);

        $this->actingAs($defender)
            ->post(route('student.arena.guild.accept', $battle))
            ->assertRedirect();

        $battle->refresh();
        $this->assertSame(TeamBattle::STATUS_RESOLVED, $battle->status);
        $this->assertNotNull($battle->winner_team_id);
        $this->assertNotEmpty($battle->log['matchups'] ?? []);

        $fighterIds = $battle->log['fighter_ids'];
        $this->assertContains($challenger->id, $fighterIds);
        $this->assertContains($defender->id, $fighterIds);

        foreach ($fighterIds as $studentId) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $studentId)
                ->firstOrFail();

            $won = $this->studentOnWinningTeam($studentId, $battle, $alpha, $beta);
            $expected = $won ? TeamBattle::GLORY_WIN : TeamBattle::GLORY_LOSS;

            $this->assertSame($expected, (int) $enrollment->glory);
            $this->assertSame($expected, (int) $enrollment->relics);
            $this->assertSame($won ? 1 : 0, (int) $enrollment->arena_wins);
            $this->assertSame($won ? 0 : 1, (int) $enrollment->arena_losses);
        }

        $this->assertSame(250, (int) $challenger->enrollmentIn($class)->fresh()->xp);
        $this->assertSame(100, (int) $defender->enrollmentIn($class)->fresh()->xp);
    }

    public function test_decline_awards_no_glory_and_does_not_consume_daily_limit(): void
    {
        Notification::fake();

        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect();

        $battle = TeamBattle::query()->firstOrFail();

        $this->actingAs($defender)
            ->post(route('student.arena.guild.decline', $battle))
            ->assertRedirect(route('student.arena.index'));

        $battle->refresh();
        $this->assertSame(TeamBattle::STATUS_DECLINED, $battle->status);

        $this->assertSame(0, (int) $challenger->enrollmentIn($class)->fresh()->glory);
        $this->assertSame(0, (int) $defender->enrollmentIn($class)->fresh()->glory);
        $this->assertSame(0, app(TeamBattleService::class)->resolvedTodayForTeam($class, $alpha));
        $this->assertSame(0, app(TeamBattleService::class)->resolvedTodayForTeam($class, $beta));

        Notification::assertSentTo($challenger, GameAlert::class);
    }

    public function test_second_battle_same_day_is_blocked(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);
        $this->resolveBattle($challenger, $defender, $beta);

        $gamma = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Gama',
            'emblem' => 'fire',
        ]);
        $extra = $this->enrollStudent($class, 'Eva Nunes', approvedPersona: true, characterClass: 'bardo');
        $gamma->members()->attach($extra->id);

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $gamma->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors(['opponent_team_id']);
    }

    public function test_guild_that_fought_today_cannot_be_challenged(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);
        $this->resolveBattle($challenger, $defender, $beta);

        $gamma = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Gama',
            'emblem' => 'owl',
        ]);
        $extra = $this->enrollStudent($class, 'Carla Dias', approvedPersona: true, characterClass: 'arqueiro');
        $gamma->members()->attach($extra->id);

        $this->actingAs($extra)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors(['opponent_team_id']);
    }

    public function test_non_member_cannot_accept(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);
        $outsider = $this->enrollStudent($class, 'Fora Silva', approvedPersona: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect();

        $battle = TeamBattle::query()->firstOrFail();

        $this->actingAs($outsider)
            ->post(route('student.arena.guild.accept', $battle))
            ->assertForbidden();
    }

    public function test_any_opponent_member_can_accept(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);
        $second = $this->enrollStudent($class, 'Beta Dois', approvedPersona: true, characterClass: 'mago');
        $beta->members()->attach($second->id);

        $this->actingAs($challenger)
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect();

        $battle = TeamBattle::query()->firstOrFail();

        $this->actingAs($second)
            ->post(route('student.arena.guild.accept', $battle))
            ->assertRedirect();

        $this->assertSame(TeamBattle::STATUS_RESOLVED, $battle->fresh()->status);
        $this->assertSame($second->id, $battle->fresh()->accepted_by_id);
    }

    public function test_pairing_three_vs_two_creates_three_matchups_and_wraps_weakest(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);

        $a2 = $this->enrollStudent($class, 'Alpha Dois', approvedPersona: true, characterClass: 'mago');
        $a3 = $this->enrollStudent($class, 'Alpha Tres', approvedPersona: true, characterClass: 'arqueiro');
        $b2 = $this->enrollStudent($class, 'Beta Dois', approvedPersona: true, characterClass: 'bardo');
        $alpha->members()->attach([$a2->id, $a3->id]);
        $beta->members()->attach($b2->id);

        $service = app(TeamBattleService::class);
        $challengerFighters = $service->eligibleFighters($class, $alpha->fresh());
        $opponentFighters = $service->eligibleFighters($class, $beta->fresh());

        $this->assertCount(3, $challengerFighters);
        $this->assertCount(2, $opponentFighters);

        $result = $service->resolveSeries(
            $class,
            $alpha,
            $beta,
            $challengerFighters,
            $opponentFighters,
            seed: 42,
        );

        $this->assertCount(3, $result['matchups']);
        $wraps = collect($result['matchups'])->where('wrap', true);
        $this->assertCount(1, $wraps);

        $weakestOpponentId = $wraps->first()['opponent_id'];
        $opponentAppearances = collect($result['matchups'])->where('opponent_id', $weakestOpponentId)->count();
        $this->assertSame(2, $opponentAppearances);
    }

    public function test_same_seed_reproduces_winner(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);
        $service = app(TeamBattleService::class);

        $left = $service->eligibleFighters($class, $alpha);
        $right = $service->eligibleFighters($class, $beta);

        $first = $service->resolveSeries($class, $alpha, $beta, $left, $right, 777);
        $second = $service->resolveSeries($class, $alpha, $beta, $left, $right, 777);

        $this->assertSame($first['winner_team_id'], $second['winner_team_id']);
        $this->assertSame($first['score'], $second['score']);
        $this->assertCount(count($first['matchups']), $second['matchups']);
    }

    public function test_pending_endpoint_includes_guild_challenges(): void
    {
        [$class, $challenger, $alpha, $beta, $defender] = $this->readyGuilds(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $beta->id])
            ->assertRedirect();

        $this->actingAs($defender)
            ->getJson(route('student.arena.pending'))
            ->assertOk()
            ->assertJsonFragment([
                'kind' => 'guild',
                'message' => 'A guilda Alpha desafiou a sua. Aceita a batalha de guildas?',
            ]);
    }

    public function test_arena_page_shows_guild_battle_block(): void
    {
        [$class, $challenger, $alpha, $beta] = $this->readyGuilds(arenaOpen: true);

        $this->actingAs($challenger)
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Batalha de Guildas')
            ->assertSee('Beta')
            ->assertSee('0/1 batalha hoje');
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: Team, 3: Team, 4: User}
     */
    private function readyGuilds(bool $arenaOpen = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => $arenaOpen]);

        $challenger = $this->enrollStudent($class, 'Ana Souza', approvedPersona: true, characterClass: 'guerreiro');
        $defender = $this->enrollStudent($class, 'Bruno Lima', approvedPersona: true, characterClass: 'mago');

        $alpha = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Alpha',
            'emblem' => 'shield',
        ]);
        $beta = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Beta',
            'emblem' => 'sword',
        ]);

        $alpha->members()->attach($challenger->id);
        $beta->members()->attach($defender->id);

        return [$class, $challenger, $alpha, $beta, $defender];
    }

    private function resolveBattle(User $challenger, User $defender, Team $opponentTeam): TeamBattle
    {
        $this->actingAs($challenger)
            ->post(route('student.arena.guild.challenge'), ['opponent_team_id' => $opponentTeam->id])
            ->assertRedirect();

        $battle = TeamBattle::query()->latest('id')->firstOrFail();

        $this->actingAs($defender)
            ->post(route('student.arena.guild.accept', $battle))
            ->assertRedirect();

        return $battle->fresh();
    }

    private function studentOnWinningTeam(int $studentId, TeamBattle $battle, Team $alpha, Team $beta): bool
    {
        $challengerIds = collect($battle->log['challenger_roster'] ?? [])->pluck('id')->all();
        $opponentIds = collect($battle->log['opponent_roster'] ?? [])->pluck('id')->all();

        if (in_array($studentId, $challengerIds, true)) {
            return $battle->winner_team_id === $alpha->id;
        }

        if (in_array($studentId, $opponentIds, true)) {
            return $battle->winner_team_id === $beta->id;
        }

        return false;
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name,
        bool $approvedPersona = true,
        string $characterClass = 'guerreiro',
    ): User {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => $characterClass,
            'must_change_password' => false,
            'character_name' => $approvedPersona ? 'Heroi '.$name : null,
            'character_avatar' => $approvedPersona ? 'lobo' : null,
            'character_approval_status' => $approvedPersona ? 'approved' : 'none',
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
