<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TeamBattle;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\ArenaUrl;
use App\Support\SeededRandom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TeamBattleService
{
    public function __construct(
        private ArenaCombatService $combat,
    ) {}

    /**
     * @throws ValidationException
     */
    public function challenge(SchoolClass $class, User $challenger, Team $opponentTeam): TeamBattle
    {
        $this->assertArenaOpen($class);

        $challengerTeam = $challenger->teamInClass($class);
        if (! $challengerTeam) {
            throw ValidationException::withMessages([
                'opponent_team_id' => 'Você precisa estar em uma guilda para desafiar outra.',
            ]);
        }

        if ($challengerTeam->class_id !== $class->id || $opponentTeam->class_id !== $class->id) {
            throw ValidationException::withMessages([
                'opponent_team_id' => 'As guildas precisam ser desta turma.',
            ]);
        }

        if ($challengerTeam->id === $opponentTeam->id) {
            throw ValidationException::withMessages([
                'opponent_team_id' => 'Você não pode desafiar a própria guilda.',
            ]);
        }

        $this->assertCanFight($class, $challenger);

        if ($reason = $this->challengeRestriction($class, $challengerTeam, $opponentTeam)) {
            throw ValidationException::withMessages([
                'opponent_team_id' => $reason,
            ]);
        }

        $this->assertTeamHasEligibleFighters($class, $challengerTeam, 'opponent_team_id');
        $this->assertTeamHasEligibleFighters($class, $opponentTeam, 'opponent_team_id');

        $battle = TeamBattle::query()->create([
            'class_id' => $class->id,
            'challenger_team_id' => $challengerTeam->id,
            'opponent_team_id' => $opponentTeam->id,
            'challenger_id' => $challenger->id,
            'status' => TeamBattle::STATUS_PENDING,
        ]);

        $challengerLabel = $challenger->arenaName() ?: $challenger->name;
        $payload = [
            'team_battle_id' => $battle->id,
            'class_id' => $class->id,
            'challenger_team_id' => $challengerTeam->id,
            'challenger_team_name' => $challengerTeam->name,
            'challenger_id' => $challenger->id,
            'challenger_name' => $challenger->name,
            'challenger_arena' => $challenger->arenaName(),
            'accept_url' => ArenaUrl::route('student.arena.guild.accept', $battle),
            'decline_url' => ArenaUrl::route('student.arena.guild.decline', $battle),
            'url' => ArenaUrl::route('student.arena.index'),
        ];

        foreach ($opponentTeam->members as $member) {
            if ($member->id === $challenger->id) {
                continue;
            }

            $member->notify(new GameAlert(
                'guild_battle_challenge',
                'Desafio de guilda',
                "A guilda {$challengerTeam->name} desafiou a sua. {$challengerLabel} enviou o desafio.",
                $payload,
            ));
        }

        return $battle;
    }

    /**
     * @throws ValidationException
     */
    public function accept(TeamBattle $battle, User $acceptor): TeamBattle
    {
        if (! $battle->isPending()) {
            throw ValidationException::withMessages([
                'battle' => 'Este desafio não está mais pendente.',
            ]);
        }

        $class = $battle->schoolClass;
        $opponentTeam = $battle->opponentTeam;
        $challengerTeam = $battle->challengerTeam;

        if (! $opponentTeam->members()->where('users.id', $acceptor->id)->exists()) {
            throw ValidationException::withMessages([
                'battle' => 'Só um membro da guilda desafiada pode aceitar.',
            ]);
        }

        $this->assertArenaOpen($class);
        $this->assertCanFight($class, $acceptor);
        $this->assertTeamHasEligibleFighters($class, $challengerTeam, 'battle');
        $this->assertTeamHasEligibleFighters($class, $opponentTeam, 'battle');

        if ($reason = $this->dailyLimitReason($class, $challengerTeam)) {
            throw ValidationException::withMessages(['battle' => $reason]);
        }

        if ($reason = $this->dailyLimitReason($class, $opponentTeam)) {
            throw ValidationException::withMessages(['battle' => $reason]);
        }

        return DB::transaction(function () use ($battle, $acceptor, $class, $challengerTeam, $opponentTeam) {
            /** @var TeamBattle $locked */
            $locked = TeamBattle::query()->whereKey($battle->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'battle' => 'Este desafio não está mais pendente.',
                ]);
            }

            $challengerFighters = $this->eligibleFighters($class, $challengerTeam);
            $opponentFighters = $this->eligibleFighters($class, $opponentTeam);

            if ($challengerFighters->isEmpty() || $opponentFighters->isEmpty()) {
                throw ValidationException::withMessages([
                    'battle' => 'Cada guilda precisa de pelo menos um lutador elegível.',
                ]);
            }

            $seed = random_int(1, PHP_INT_MAX);
            $result = $this->resolveSeries($class, $challengerTeam, $opponentTeam, $challengerFighters, $opponentFighters, $seed);

            $locked->update([
                'status' => TeamBattle::STATUS_RESOLVED,
                'accepted_by_id' => $acceptor->id,
                'seed' => $seed,
                'log' => $result,
                'winner_team_id' => $result['winner_team_id'],
                'glory_winner' => TeamBattle::GLORY_WIN,
                'glory_loser' => TeamBattle::GLORY_LOSS,
                'resolved_at' => now(),
            ]);

            $winnerTeamId = $result['winner_team_id'];
            $fighterIds = $result['fighter_ids'];
            $challengerFighterIds = collect($result['challenger_roster'])->pluck('id')->all();
            $opponentFighterIds = collect($result['opponent_roster'])->pluck('id')->all();

            foreach ($fighterIds as $studentId) {
                $onChallengerSide = in_array($studentId, $challengerFighterIds, true);
                $fighterTeamId = $onChallengerSide ? $challengerTeam->id : $opponentTeam->id;
                $won = $fighterTeamId === $winnerTeamId;
                $this->awardGlory(
                    $class,
                    $studentId,
                    $won ? TeamBattle::GLORY_WIN : TeamBattle::GLORY_LOSS,
                );
            }

            $this->notifyBattleResult($locked->fresh([
                'challengerTeam',
                'opponentTeam',
                'winnerTeam',
                'challenger',
            ]), $class, $fighterIds);

            return $locked->fresh([
                'challengerTeam',
                'opponentTeam',
                'winnerTeam',
                'challenger',
                'acceptedBy',
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function decline(TeamBattle $battle, User $decliner): TeamBattle
    {
        if (! $battle->isPending()) {
            throw ValidationException::withMessages([
                'battle' => 'Este desafio não está mais pendente.',
            ]);
        }

        $opponentTeam = $battle->opponentTeam;

        if (! $opponentTeam->members()->where('users.id', $decliner->id)->exists()) {
            throw ValidationException::withMessages([
                'battle' => 'Só um membro da guilda desafiada pode recusar.',
            ]);
        }

        $battle->update([
            'status' => TeamBattle::STATUS_DECLINED,
            'accepted_by_id' => $decliner->id,
            'resolved_at' => now(),
        ]);

        $declinerLabel = $decliner->arenaName() ?: $decliner->name;
        $challengerTeam = $battle->challengerTeam;

        foreach ($challengerTeam->members as $member) {
            $member->notify(new GameAlert(
                'guild_battle_declined',
                'Desafio de guilda recusado',
                "{$declinerLabel} recusou o desafio da guilda {$opponentTeam->name}. Sem punição.",
                [
                    'team_battle_id' => $battle->id,
                    'class_id' => $battle->class_id,
                    'url' => ArenaUrl::route('student.arena.index'),
                ],
            ));
        }

        return $battle->fresh(['challengerTeam', 'opponentTeam', 'challenger', 'acceptedBy']);
    }

    public function challengeRestriction(SchoolClass $class, Team $challengerTeam, Team $opponentTeam): ?string
    {
        return $this->pendingForTeamReason($class, $challengerTeam)
            ?? $this->pendingForTeamReason($class, $opponentTeam)
            ?? $this->dailyLimitReason($class, $challengerTeam)
            ?? $this->dailyLimitReason($class, $opponentTeam);
    }

    /**
     * @param  iterable<int, Team>  $teams
     * @return array<int, string>
     */
    public function challengeNotices(SchoolClass $class, Team $challengerTeam, iterable $teams): array
    {
        $notices = [];

        foreach ($teams as $team) {
            $reason = $this->challengeRestriction($class, $challengerTeam, $team);
            if ($reason !== null) {
                $notices[$team->id] = $reason;
            }
        }

        return $notices;
    }

    public function resolvedTodayForTeam(SchoolClass $class, Team $team): int
    {
        return TeamBattle::query()
            ->where('class_id', $class->id)
            ->where('status', TeamBattle::STATUS_RESOLVED)
            ->where(function ($query) use ($team) {
                $query->where('challenger_team_id', $team->id)
                    ->orWhere('opponent_team_id', $team->id);
            })
            ->whereDate('resolved_at', Carbon::today())
            ->count();
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleFighters(SchoolClass $class, Team $team): Collection
    {
        return $team->members()
            ->orderBy('name')
            ->get()
            ->filter(fn (User $member) => $this->canFight($class, $member))
            ->values();
    }

    /**
     * @param  Collection<int, User>  $challengerFighters
     * @param  Collection<int, User>  $opponentFighters
     * @return array{
     *     winner_team_id: int,
     *     winner_reason: string,
     *     score: array{challenger: int, opponent: int},
     *     hp_tiebreak: array{challenger: int, opponent: int},
     *     fighter_ids: list<int>,
     *     challenger_roster: list<array<string, mixed>>,
     *     opponent_roster: list<array<string, mixed>>,
     *     matchups: list<array<string, mixed>>
     * }
     */
    public function resolveSeries(
        SchoolClass $class,
        Team $challengerTeam,
        Team $opponentTeam,
        Collection $challengerFighters,
        Collection $opponentFighters,
        int $seed,
    ): array {
        $challengerSorted = $this->sortByPower($class, $challengerFighters);
        $opponentSorted = $this->sortByPower($class, $opponentFighters);

        $pairs = $this->buildMatchups($challengerSorted, $opponentSorted);
        $rng = new SeededRandom($seed);

        $matchups = [];
        $challengerWins = 0;
        $opponentWins = 0;
        $challengerWinnerHp = 0;
        $opponentWinnerHp = 0;
        $fighterIds = [];

        foreach ($pairs as $index => $pair) {
            $fightSeed = $rng->nextInt(1, PHP_INT_MAX);
            $result = $this->combat->resolve($pair['challenger'], $pair['opponent'], $class, $fightSeed);

            $winnerId = $result['winner_id'];
            $winnerIsChallenger = $winnerId === $pair['challenger']->id;
            if ($winnerIsChallenger) {
                $challengerWins++;
                $challengerWinnerHp += (int) ($result['fighters']['challenger']['hp'] ?? 0);
            } else {
                $opponentWins++;
                $opponentWinnerHp += (int) ($result['fighters']['opponent']['hp'] ?? 0);
            }

            $fighterIds[$pair['challenger']->id] = true;
            $fighterIds[$pair['opponent']->id] = true;

            $matchups[] = [
                'index' => $index + 1,
                'seed' => $fightSeed,
                'challenger_id' => $pair['challenger']->id,
                'opponent_id' => $pair['opponent']->id,
                'winner_id' => $winnerId,
                'winner_reason' => $result['winner_reason'],
                'wrap' => $pair['wrap'],
                'turns' => $result['turns'],
                'fighters' => $result['fighters'],
            ];
        }

        [$winnerTeamId, $winnerReason] = $this->decideSeriesWinner(
            $challengerTeam->id,
            $opponentTeam->id,
            $challengerWins,
            $opponentWins,
            $challengerWinnerHp,
            $opponentWinnerHp,
        );

        return [
            'winner_team_id' => $winnerTeamId,
            'winner_reason' => $winnerReason,
            'score' => [
                'challenger' => $challengerWins,
                'opponent' => $opponentWins,
            ],
            'hp_tiebreak' => [
                'challenger' => $challengerWinnerHp,
                'opponent' => $opponentWinnerHp,
            ],
            'fighter_ids' => array_map('intval', array_keys($fighterIds)),
            'challenger_roster' => $challengerSorted->map(fn (User $u) => $this->rosterEntry($u, $class))->all(),
            'opponent_roster' => $opponentSorted->map(fn (User $u) => $this->rosterEntry($u, $class))->all(),
            'matchups' => $matchups,
        ];
    }

    /**
     * @param  Collection<int, User>  $challengers
     * @param  Collection<int, User>  $opponents
     * @return list<array{challenger: User, opponent: User, wrap: bool}>
     */
    public function buildMatchups(Collection $challengers, Collection $opponents): array
    {
        $left = $challengers->values();
        $right = $opponents->values();
        $pairs = [];
        $count = min($left->count(), $right->count());

        for ($i = 0; $i < $count; $i++) {
            $pairs[] = [
                'challenger' => $left[$i],
                'opponent' => $right[$i],
                'wrap' => false,
            ];
        }

        if ($left->count() > $right->count()) {
            $weakest = $right->last();
            for ($i = $count; $i < $left->count(); $i++) {
                $pairs[] = [
                    'challenger' => $left[$i],
                    'opponent' => $weakest,
                    'wrap' => true,
                ];
            }
        } elseif ($right->count() > $left->count()) {
            $weakest = $left->last();
            for ($i = $count; $i < $right->count(); $i++) {
                $pairs[] = [
                    'challenger' => $weakest,
                    'opponent' => $right[$i],
                    'wrap' => true,
                ];
            }
        }

        return $pairs;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function decideSeriesWinner(
        int $challengerTeamId,
        int $opponentTeamId,
        int $challengerWins,
        int $opponentWins,
        int $challengerWinnerHp,
        int $opponentWinnerHp,
    ): array {
        if ($challengerWins > $opponentWins) {
            return [$challengerTeamId, 'score'];
        }

        if ($opponentWins > $challengerWins) {
            return [$opponentTeamId, 'score'];
        }

        if ($challengerWinnerHp > $opponentWinnerHp) {
            return [$challengerTeamId, 'hp'];
        }

        if ($opponentWinnerHp > $challengerWinnerHp) {
            return [$opponentTeamId, 'hp'];
        }

        return [$challengerTeamId, 'challenger_tie'];
    }

    /**
     * @param  Collection<int, User>  $fighters
     * @return Collection<int, User>
     */
    private function sortByPower(SchoolClass $class, Collection $fighters): Collection
    {
        return $fighters
            ->sort(function (User $a, User $b) use ($class) {
                $powerA = $this->combat->buildFighter($a, $class)['power'];
                $powerB = $this->combat->buildFighter($b, $class)['power'];

                return [$powerB, $a->name] <=> [$powerA, $b->name];
            })
            ->values();
    }

    /**
     * @return array{id: int, name: string, arena_name: ?string, class: string, power: float}
     */
    private function rosterEntry(User $student, SchoolClass $class): array
    {
        $fighter = $this->combat->buildFighter($student, $class);

        return [
            'id' => $student->id,
            'name' => $student->name,
            'arena_name' => $student->arenaName(),
            'class' => $fighter['class'],
            'power' => $fighter['power'],
        ];
    }

    /**
     * @param  list<int>  $fighterIds
     */
    private function notifyBattleResult(TeamBattle $battle, SchoolClass $class, array $fighterIds): void
    {
        $winnerTeam = $battle->winnerTeam;
        $winnerName = $winnerTeam?->name ?? 'Guilda';
        $fighterSet = array_fill_keys($fighterIds, true);

        foreach ([$battle->challengerTeam, $battle->opponentTeam] as $team) {
            $teamWon = $team->id === $battle->winner_team_id;

            foreach ($team->members as $member) {
                $fought = isset($fighterSet[$member->id]);
                $message = $teamWon
                    ? ($fought
                        ? "A guilda {$winnerName} venceu! Você ganhou ".TeamBattle::GLORY_WIN.' de Glória.'
                        : "A guilda {$winnerName} venceu a batalha.")
                    : ($fought
                        ? "A guilda {$winnerName} venceu. Você ganhou ".TeamBattle::GLORY_LOSS.' de Glória.'
                        : "A guilda {$winnerName} venceu a batalha.");

                $member->notify(new GameAlert(
                    'guild_battle_result',
                    'Batalha de guilda resolvida',
                    $message,
                    [
                        'team_battle_id' => $battle->id,
                        'class_id' => $class->id,
                        'url' => ArenaUrl::route('student.arena.guild.show', $battle),
                    ],
                ));
            }
        }
    }

    private function awardGlory(SchoolClass $class, int $studentId, int $amount): void
    {
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            throw new RuntimeException('Matrícula não encontrada para premiar Glória.');
        }

        $enrollment->glory = (int) $enrollment->glory + $amount;
        $enrollment->relics = (int) $enrollment->relics + $amount;
        $enrollment->save();
    }

    private function assertArenaOpen(SchoolClass $class): void
    {
        if (! $class->isArenaOpen()) {
            throw ValidationException::withMessages([
                'arena' => 'A arena desta turma está fechada.',
            ]);
        }
    }

    private function assertCanFight(SchoolClass $class, User $student): void
    {
        if (! $this->canFight($class, $student)) {
            throw ValidationException::withMessages([
                'opponent_team_id' => 'Avatar e nome de jogo precisam estar aprovados para batalhar.',
            ]);
        }
    }

    private function canFight(SchoolClass $class, User $student): bool
    {
        return $student->isStudent()
            && $student->enrollmentIn($class) !== null
            && $student->hasCharacterClass()
            && $student->hasApprovedPersona();
    }

    private function assertTeamHasEligibleFighters(SchoolClass $class, Team $team, string $field): void
    {
        if ($this->eligibleFighters($class, $team)->isEmpty()) {
            throw ValidationException::withMessages([
                $field => "A guilda {$team->name} não tem lutadores elegíveis.",
            ]);
        }
    }

    private function dailyLimitReason(SchoolClass $class, Team $team): ?string
    {
        if ($this->resolvedTodayForTeam($class, $team) >= TeamBattle::DAILY_RESOLVED_LIMIT) {
            return "A guilda {$team->name} já batalhou hoje. Só pode de novo amanhã.";
        }

        return null;
    }

    private function pendingForTeamReason(SchoolClass $class, Team $team): ?string
    {
        $exists = TeamBattle::query()
            ->where('class_id', $class->id)
            ->where('status', TeamBattle::STATUS_PENDING)
            ->where(function ($query) use ($team) {
                $query->where('challenger_team_id', $team->id)
                    ->orWhere('opponent_team_id', $team->id);
            })
            ->exists();

        if ($exists) {
            return "A guilda {$team->name} já tem um desafio pendente.";
        }

        return null;
    }
}
