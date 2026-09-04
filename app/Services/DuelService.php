<?php

namespace App\Services;

use App\Models\Duel;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\ArenaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DuelService
{
    public function __construct(
        private ArenaCombatService $combat,
    ) {}

    /**
     * @throws ValidationException
     */
    public function challenge(SchoolClass $class, User $challenger, User $opponent): Duel
    {
        $this->assertArenaOpen($class);
        $this->assertCanFight($class, $challenger);
        $this->assertCanFight($class, $opponent);

        if ($challenger->id === $opponent->id) {
            throw ValidationException::withMessages([
                'opponent_id' => 'Você não pode desafiar a si mesmo.',
            ]);
        }

        if ($reason = $this->challengeRestriction($class, $challenger, $opponent)) {
            throw ValidationException::withMessages([
                'opponent_id' => $reason,
            ]);
        }

        $duel = Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'status' => Duel::STATUS_PENDING,
        ]);

        $challengerLabel = $challenger->arenaName() ?: $challenger->name;

        $opponent->notify(new GameAlert(
            'duel_challenge',
            'Desafio na arena',
            "{$challengerLabel} te desafiou para um duelo.",
            [
                'duel_id' => $duel->id,
                'class_id' => $class->id,
                'challenger_id' => $challenger->id,
                'challenger_name' => $challenger->name,
                'challenger_arena' => $challenger->arenaName(),
                'accept_url' => ArenaUrl::route('student.arena.accept', $duel),
                'decline_url' => ArenaUrl::route('student.arena.decline', $duel),
                'url' => ArenaUrl::route('student.arena.index'),
            ],
        ));

        return $duel;
    }

    /**
     * @throws ValidationException
     */
    public function accept(Duel $duel, User $opponent): Duel
    {
        if (! $duel->isPending()) {
            throw ValidationException::withMessages([
                'duel' => 'Este desafio não está mais pendente.',
            ]);
        }

        if ($duel->opponent_id !== $opponent->id) {
            throw ValidationException::withMessages([
                'duel' => 'Só o desafiado pode aceitar este duelo.',
            ]);
        }

        $class = $duel->schoolClass;
        $this->assertArenaOpen($class);
        $this->assertCanFight($class, $duel->challenger);
        $this->assertCanFight($class, $opponent);
        $this->assertDailyResolvedLimit($class, $duel->challenger);
        $this->assertDailyResolvedLimit($class, $opponent);

        return DB::transaction(function () use ($duel, $opponent, $class) {
            /** @var Duel $locked */
            $locked = Duel::query()->whereKey($duel->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'duel' => 'Este desafio não está mais pendente.',
                ]);
            }

            $seed = random_int(1, PHP_INT_MAX);
            $result = $this->combat->resolve($locked->challenger, $opponent, $class, $seed);
            $winnerId = $result['winner_id'];
            $loserId = $winnerId === $locked->challenger_id
                ? $locked->opponent_id
                : $locked->challenger_id;

            $locked->update([
                'status' => Duel::STATUS_RESOLVED,
                'seed' => $seed,
                'log' => $result,
                'winner_id' => $winnerId,
                'glory_winner' => Duel::GLORY_WIN,
                'glory_loser' => Duel::GLORY_LOSS,
                'resolved_at' => now(),
            ]);

            $this->awardGlory($class, $winnerId, Duel::GLORY_WIN, won: true);
            $this->awardGlory($class, $loserId, Duel::GLORY_LOSS, won: false);

            $winner = User::query()->findOrFail($winnerId);
            $loser = User::query()->findOrFail($loserId);
            $winnerLabel = $winner->arenaName() ?: $winner->name;

            $locked->challenger->notify(new GameAlert(
                'duel_result',
                'Duelo resolvido',
                $winnerId === $locked->challenger_id
                    ? 'Você venceu o duelo e ganhou '.Duel::GLORY_WIN.' de Glória!'
                    : "{$winnerLabel} venceu o duelo. Você ganhou ".Duel::GLORY_LOSS.' de Glória.',
                [
                    'duel_id' => $locked->id,
                    'class_id' => $class->id,
                    'url' => ArenaUrl::route('student.arena.show', $locked),
                ],
            ));

            $opponent->notify(new GameAlert(
                'duel_result',
                'Duelo resolvido',
                $winnerId === $opponent->id
                    ? 'Você venceu o duelo e ganhou '.Duel::GLORY_WIN.' de Glória!'
                    : "{$winnerLabel} venceu o duelo. Você ganhou ".Duel::GLORY_LOSS.' de Glória.',
                [
                    'duel_id' => $locked->id,
                    'class_id' => $class->id,
                    'url' => ArenaUrl::route('student.arena.show', $locked),
                ],
            ));

            return $locked->fresh(['challenger', 'opponent', 'winner']);
        });
    }

    /**
     * @throws ValidationException
     */
    public function decline(Duel $duel, User $opponent): Duel
    {
        if (! $duel->isPending()) {
            throw ValidationException::withMessages([
                'duel' => 'Este desafio não está mais pendente.',
            ]);
        }

        if ($duel->opponent_id !== $opponent->id) {
            throw ValidationException::withMessages([
                'duel' => 'Só o desafiado pode recusar este duelo.',
            ]);
        }

        $duel->update([
            'status' => Duel::STATUS_DECLINED,
            'resolved_at' => now(),
        ]);

        $opponentLabel = $opponent->arenaName() ?: $opponent->name;

        $duel->challenger->notify(new GameAlert(
            'duel_declined',
            'Desafio recusado',
            "{$opponentLabel} recusou o duelo. Sem punição.",
            [
                'duel_id' => $duel->id,
                'class_id' => $duel->class_id,
                'url' => ArenaUrl::route('student.arena.index'),
            ],
        ));

        return $duel->fresh(['challenger', 'opponent']);
    }

    public function toggleArena(SchoolClass $class, bool $open): SchoolClass
    {
        $class->update(['arena_open' => $open]);

        return $class->fresh();
    }

    /**
     * @return list<array{position: int, student: User, glory: int, arena_wins: int, arena_losses: int, visible: bool}>
     */
    public function hall(SchoolClass $class, bool $publicOnly = false): array
    {
        $rows = [];

        foreach ($class->students()->orderBy('name')->get() as $student) {
            $glory = (int) ($student->pivot?->glory ?? 0);
            $wins = (int) ($student->pivot?->arena_wins ?? 0);
            $losses = (int) ($student->pivot?->arena_losses ?? 0);

            if ($glory === 0 && $wins === 0 && $losses === 0) {
                continue;
            }

            $rows[] = [
                'student' => $student,
                'glory' => $glory,
                'arena_wins' => $wins,
                'arena_losses' => $losses,
                'visible' => (bool) ($student->pivot?->ranking_visible ?? false),
            ];
        }

        usort($rows, function (array $a, array $b) {
            return [$b['glory'], $b['arena_wins'], $a['student']->name]
                <=> [$a['glory'], $a['arena_wins'], $b['student']->name];
        });

        $ranked = [];
        foreach (array_values($rows) as $index => $row) {
            $row['position'] = $index + 1;
            $ranked[] = $row;
        }

        if ($publicOnly) {
            return array_values(array_filter($ranked, fn (array $row) => $row['visible']));
        }

        return $ranked;
    }

    /**
     * Motivo pelo qual este desafio não pode ser enviado agora.
     */
    public function challengeRestriction(SchoolClass $class, User $challenger, User $opponent): ?string
    {
        return $this->pendingBetweenReason($class, $challenger, $opponent)
            ?? $this->duplicateTodayReason($class, $challenger, $opponent)
            ?? $this->dailyLimitReason($class, $challenger)
            ?? $this->cooldownReason($class, $challenger);
    }

    /**
     * @param  iterable<int, User>  $opponents
     * @return array<int, string>
     */
    public function challengeNotices(SchoolClass $class, User $challenger, iterable $opponents): array
    {
        $notices = [];

        foreach ($opponents as $opponent) {
            $reason = $this->challengeRestriction($class, $challenger, $opponent);
            if ($reason !== null) {
                $notices[$opponent->id] = $reason;
            }
        }

        return $notices;
    }

    /**
     * Contagem de duelos resolvidos hoje envolvendo o aluno.
     */
    public function resolvedTodayCount(SchoolClass $class, User $student): int
    {
        return Duel::query()
            ->where('class_id', $class->id)
            ->where('status', Duel::STATUS_RESOLVED)
            ->where(function ($query) use ($student) {
                $query->where('challenger_id', $student->id)
                    ->orWhere('opponent_id', $student->id);
            })
            ->whereDate('resolved_at', Carbon::today())
            ->count();
    }

    private function awardGlory(SchoolClass $class, int $studentId, int $amount, bool $won): void
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

        if ($won) {
            $enrollment->arena_wins = (int) $enrollment->arena_wins + 1;
        } else {
            $enrollment->arena_losses = (int) $enrollment->arena_losses + 1;
        }

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
        if (! $student->isStudent()) {
            throw ValidationException::withMessages([
                'opponent_id' => 'Apenas alunos podem duelar.',
            ]);
        }

        if (! $student->enrollmentIn($class)) {
            throw ValidationException::withMessages([
                'opponent_id' => 'O aluno precisa estar matriculado nesta turma.',
            ]);
        }

        if (! $student->hasCharacterClass()) {
            throw ValidationException::withMessages([
                'opponent_id' => 'É preciso escolher uma classe de personagem.',
            ]);
        }

        if (! $student->hasApprovedPersona()) {
            throw ValidationException::withMessages([
                'opponent_id' => 'Avatar e nome de jogo precisam estar aprovados.',
            ]);
        }
    }

    private function assertDailyResolvedLimit(SchoolClass $class, User $student): void
    {
        if ($reason = $this->dailyLimitReason($class, $student)) {
            throw ValidationException::withMessages([
                'opponent_id' => $reason,
            ]);
        }
    }

    private function dailyLimitReason(SchoolClass $class, User $student): ?string
    {
        if ($this->resolvedTodayCount($class, $student) >= Duel::DAILY_RESOLVED_LIMIT) {
            return 'Você já fez '.Duel::DAILY_RESOLVED_LIMIT.' duelos hoje. Só pode duelar de novo amanhã.';
        }

        return null;
    }

    private function cooldownReason(SchoolClass $class, User $challenger): ?string
    {
        $since = now()->subHours(Duel::CHALLENGE_COOLDOWN_HOURS);

        $recent = Duel::query()
            ->where('class_id', $class->id)
            ->where('challenger_id', $challenger->id)
            ->where('created_at', '>=', $since)
            ->whereIn('status', [Duel::STATUS_PENDING, Duel::STATUS_RESOLVED])
            ->exists();

        if ($recent) {
            return 'Aguarde '.Duel::CHALLENGE_COOLDOWN_HOURS.' horas entre um desafio e outro.';
        }

        return null;
    }

    private function duplicateTodayReason(SchoolClass $class, User $challenger, User $opponent): ?string
    {
        $exists = Duel::query()
            ->where('class_id', $class->id)
            ->whereDate('created_at', Carbon::today())
            ->where(function ($query) use ($challenger, $opponent) {
                $query->where(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $challenger->id)->where('opponent_id', $opponent->id);
                })->orWhere(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $opponent->id)->where('opponent_id', $challenger->id);
                });
            })
            ->whereIn('status', [Duel::STATUS_PENDING, Duel::STATUS_RESOLVED])
            ->exists();

        if ($exists) {
            return 'Você já desafiou '.$this->fighterLabel($opponent).' hoje. Só pode duelar de novo amanhã.';
        }

        return null;
    }

    private function pendingBetweenReason(SchoolClass $class, User $challenger, User $opponent): ?string
    {
        $exists = Duel::query()
            ->where('class_id', $class->id)
            ->where('status', Duel::STATUS_PENDING)
            ->where(function ($query) use ($challenger, $opponent) {
                $query->where(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $challenger->id)->where('opponent_id', $opponent->id);
                })->orWhere(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $opponent->id)->where('opponent_id', $challenger->id);
                });
            })
            ->exists();

        if ($exists) {
            return 'Já existe um desafio pendente com '.$this->fighterLabel($opponent).'. Aguarde a resposta.';
        }

        return null;
    }

    private function fighterLabel(User $student): string
    {
        return $student->arenaName() ?: $student->name;
    }
}
