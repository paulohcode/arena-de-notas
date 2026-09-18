<?php

namespace App\Services;

use App\Models\Duel;
use App\Models\Enrollment;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\TeamBattle;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\ArenaSchedule;
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

        $resolved = DB::transaction(function () use ($duel, $opponent, $class) {
            /** @var Duel $locked */
            $locked = Duel::query()->whereKey($duel->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'duel' => 'Este desafio não está mais pendente.',
                ]);
            }

            return $this->finalizeCombat($locked, $locked->challenger, $opponent, $class);
        });

        $this->expirePendingIncomingAtDailyLimit($resolved->challenger);
        $this->expirePendingIncomingAtDailyLimit($resolved->opponent);

        return $resolved;
    }

    /**
     * Professor marca um duelo entre dois alunos; combate resolve na hora.
     *
     * @throws ValidationException
     */
    public function staffArrange(SchoolClass $class, User $fighterA, User $fighterB, User $teacher): Duel
    {
        $this->assertCanFight($class, $fighterA);
        $this->assertCanFight($class, $fighterB);

        if ($fighterA->id === $fighterB->id) {
            throw ValidationException::withMessages([
                'opponent_id' => 'Escolha dois alunos diferentes.',
            ]);
        }

        return DB::transaction(function () use ($class, $fighterA, $fighterB, $teacher) {
            $duel = Duel::query()->create([
                'class_id' => $class->id,
                'challenger_id' => $fighterA->id,
                'opponent_id' => $fighterB->id,
                'arranged_by' => $teacher->id,
                'status' => Duel::STATUS_PENDING,
            ]);

            /** @var Duel $locked */
            $locked = Duel::query()->whereKey($duel->id)->lockForUpdate()->firstOrFail();

            return $this->finalizeCombat($locked, $fighterA, $fighterB, $class);
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

        return DB::transaction(function () use ($duel, $opponent) {
            /** @var Duel $locked */
            $locked = Duel::query()->whereKey($duel->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'duel' => 'Este desafio não está mais pendente.',
                ]);
            }

            $locked->update([
                'status' => Duel::STATUS_DECLINED,
                'resolved_at' => now(),
            ]);

            $this->applyDeclinePenalty($locked->schoolClass, $opponent);

            $opponentLabel = $opponent->arenaName() ?: $opponent->name;
            $gloryLabel = GameCurrency::label('glory');
            $relicsLabel = GameCurrency::label('relics');

            $locked->challenger->notify(new GameAlert(
                'duel_declined',
                'Desafio recusado',
                "{$opponentLabel} recusou o duelo (−".Duel::DECLINE_PENALTY_GLORY." {$gloryLabel} e −".Duel::DECLINE_PENALTY_RELICS." {$relicsLabel}).",
                [
                    'duel_id' => $locked->id,
                    'class_id' => $locked->class_id,
                    'url' => ArenaUrl::route('student.arena.index'),
                ],
            ));

            $opponent->notify(new GameAlert(
                'duel_declined_self',
                'Você recusou o duelo',
                'Recusar custa −'.Duel::DECLINE_PENALTY_GLORY." {$gloryLabel} e −".Duel::DECLINE_PENALTY_RELICS." {$relicsLabel}. Aceitar e perder ainda rende +".Duel::GLORY_LOSS.' de cada.',
                [
                    'duel_id' => $locked->id,
                    'class_id' => $locked->class_id,
                    'url' => ArenaUrl::route('student.arena.index'),
                ],
            ));

            return $locked->fresh(['challenger', 'opponent']);
        });
    }

    /**
     * Multa por recusar ou deixar o desafio expirar (piso 0; não altera W/L).
     */
    public function applyDeclinePenalty(SchoolClass $class, User $student): void
    {
        $this->debitCurrencies(
            $class,
            $student->id,
            Duel::DECLINE_PENALTY_GLORY,
            Duel::DECLINE_PENALTY_RELICS,
        );
    }

    public function toggleArena(SchoolClass $class, bool $open): SchoolClass
    {
        if (! $class->hasArenaSchedule()) {
            $class->update(['arena_open' => $open]);

            return $class->fresh();
        }

        $class->update([
            'arena_schedule' => ArenaSchedule::toggleToday(
                $class->arena_schedule,
                $open,
                (bool) $class->arena_open,
                (int) ($class->arena_cooldown_minutes ?? Duel::CHALLENGE_COOLDOWN_MINUTES),
                (int) ($class->arena_daily_limit ?? Duel::DAILY_RESOLVED_LIMIT),
            ),
            'arena_open' => $open,
        ]);

        return $class->fresh();
    }

    /**
     * @param  array<int, array{open: bool, cooldown_minutes: int, daily_limit: int}>  $schedule
     * @param  array<int, array{daily_limit: int}>  $guildSchedule
     */
    public function updateSettings(
        SchoolClass $class,
        array $schedule,
        array $guildSchedule,
        ?int $weeklyQuota = null,
    ): SchoolClass {
        $today = ArenaSchedule::forToday(
            $schedule,
            false,
            Duel::CHALLENGE_COOLDOWN_MINUTES,
            Duel::DAILY_RESOLVED_LIMIT,
        );

        $payload = [
            'arena_schedule' => $schedule,
            'arena_open' => $today['open'],
            'arena_cooldown_minutes' => $today['cooldown_minutes'],
            'arena_daily_limit' => $today['daily_limit'],
            'guild_arena_schedule' => $guildSchedule,
            'guild_arena_daily_limit' => ArenaSchedule::guildLimitForToday(
                $guildSchedule,
                TeamBattle::DAILY_RESOLVED_LIMIT,
            ),
        ];

        if ($weeklyQuota !== null) {
            $payload['arena_weekly_quota'] = max(0, $weeklyQuota);
        }

        $class->update($payload);

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
            ?? $this->dailyLimitReason($class, $opponent, $this->fighterLabel($opponent))
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
     * Encerra desafios recebidos que o aluno já não pode aceitar hoje.
     */
    public function expirePendingIncomingAtDailyLimit(User $student): void
    {
        DB::transaction(function () use ($student) {
            $duels = Duel::query()
                ->with(['challenger', 'opponent', 'schoolClass'])
                ->where('opponent_id', $student->id)
                ->where('status', Duel::STATUS_PENDING)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($duels->groupBy('class_id') as $classDuels) {
                $class = $classDuels->first()?->schoolClass;

                if (! $class || $this->dailyLimitReason($class, $student) === null) {
                    continue;
                }

                foreach ($classDuels as $duel) {
                    $duel->update([
                        'status' => Duel::STATUS_EXPIRED,
                        'resolved_at' => now(),
                    ]);

                    $this->applyDeclinePenalty($class, $student);

                    $opponentLabel = $this->fighterLabel($student);
                    $gloryLabel = GameCurrency::label('glory');
                    $relicsLabel = GameCurrency::label('relics');

                    $duel->challenger->notify(new GameAlert(
                        'duel_expired',
                        'Desafio expirado',
                        "{$opponentLabel} já atingiu o limite de duelos de hoje (−".Duel::DECLINE_PENALTY_GLORY." {$gloryLabel} e −".Duel::DECLINE_PENALTY_RELICS." {$relicsLabel} para quem não respondeu).",
                        [
                            'duel_id' => $duel->id,
                            'class_id' => $duel->class_id,
                            'url' => ArenaUrl::route('student.arena.index'),
                        ],
                    ));
                }
            }
        });
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

    /**
     * Contagem de duelos resolvidos na semana civil (segunda–domingo).
     */
    public function resolvedInWeekCount(SchoolClass $class, User $student, ?Carbon $reference = null): int
    {
        $reference ??= now();
        $start = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $end = $reference->copy()->endOfWeek(Carbon::SUNDAY);

        return Duel::query()
            ->where('class_id', $class->id)
            ->where('status', Duel::STATUS_RESOLVED)
            ->where(function ($query) use ($student) {
                $query->where('challenger_id', $student->id)
                    ->orWhere('opponent_id', $student->id);
            })
            ->whereBetween('resolved_at', [$start, $end])
            ->count();
    }

    /**
     * Chave ISO da semana (ex.: 2026-W38).
     */
    public function weekKey(?Carbon $reference = null): string
    {
        $reference ??= now();

        return sprintf('%d-W%02d', (int) $reference->isoWeekYear, (int) $reference->isoWeek);
    }

    /**
     * Aplica multa de cota da semana anterior (idempotente por turma).
     */
    public function settleWeeklyQuotas(): int
    {
        $previousWeek = now()->copy()->subWeek();
        $previousWeekKey = $this->weekKey($previousWeek);
        $penalized = 0;

        $classes = SchoolClass::query()
            ->where('arena_weekly_quota', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($classes as $class) {
            $penalized += $this->settleWeeklyQuotaForClass($class, $previousWeek, $previousWeekKey);
        }

        return $penalized;
    }

    /**
     * @return list<array{student: User, count: int, quota: int}>
     */
    public function weeklyQuotaProgress(SchoolClass $class): array
    {
        $quota = $class->arenaWeeklyQuota();

        if ($quota <= 0) {
            return [];
        }

        $rows = [];

        foreach ($class->students()->orderBy('name')->get() as $student) {
            if (! $student->hasApprovedPersona()) {
                continue;
            }

            $rows[] = [
                'student' => $student,
                'count' => $this->resolvedInWeekCount($class, $student),
                'quota' => $quota,
            ];
        }

        return $rows;
    }

    /**
     * Bônus de zebra: vitória com poder base menor que o do rival.
     */
    public function upsetBonus(float $winnerPower, float $loserPower): int
    {
        if ($winnerPower <= 0 || $loserPower <= $winnerPower) {
            return 0;
        }

        $ratio = $loserPower / $winnerPower;

        if ($ratio >= 1.40) {
            return 15;
        }

        if ($ratio >= 1.20) {
            return 10;
        }

        if ($ratio >= 1.05) {
            return 5;
        }

        return 0;
    }

    private function settleWeeklyQuotaForClass(SchoolClass $class, Carbon $previousWeek, string $previousWeekKey): int
    {
        return (int) DB::transaction(function () use ($class, $previousWeek, $previousWeekKey) {
            /** @var SchoolClass $locked */
            $locked = SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $quota = $locked->arenaWeeklyQuota();

            if ($quota <= 0) {
                return 0;
            }

            if ($locked->arena_quota_settled_week === null) {
                $locked->update(['arena_quota_settled_week' => $previousWeekKey]);

                return 0;
            }

            if ($locked->arena_quota_settled_week === $previousWeekKey) {
                return 0;
            }

            $penalized = 0;
            $gloryLabel = GameCurrency::label('glory');
            $relicsLabel = GameCurrency::label('relics');

            foreach ($locked->students()->orderBy('name')->get() as $student) {
                if (! $student->hasApprovedPersona()) {
                    continue;
                }

                $count = $this->resolvedInWeekCount($locked, $student, $previousWeek);

                if ($count >= $quota) {
                    continue;
                }

                $this->debitCurrencies(
                    $locked,
                    $student->id,
                    Duel::WEEKLY_QUOTA_PENALTY,
                    Duel::WEEKLY_QUOTA_PENALTY,
                );

                $student->notify(new GameAlert(
                    'duel_quota_penalty',
                    'Cota semanal da arena',
                    "Você fez {$count} de {$quota} duelos na semana passada. Multa: −".Duel::WEEKLY_QUOTA_PENALTY." {$gloryLabel} e −".Duel::WEEKLY_QUOTA_PENALTY." {$relicsLabel}.",
                    [
                        'class_id' => $locked->id,
                        'url' => ArenaUrl::route('student.arena.index'),
                    ],
                ));

                $penalized++;
            }

            $locked->update(['arena_quota_settled_week' => $previousWeekKey]);

            return $penalized;
        });
    }

    private function finalizeCombat(Duel $locked, User $challenger, User $opponent, SchoolClass $class): Duel
    {
        $seed = random_int(1, PHP_INT_MAX);
        $challengerPower = (float) $this->combat->buildFighter($challenger, $class)['power'];
        $opponentPower = (float) $this->combat->buildFighter($opponent, $class)['power'];
        $result = $this->combat->resolve($challenger, $opponent, $class, $seed);
        $winnerId = $result['winner_id'];
        $loserId = $winnerId === $locked->challenger_id
            ? $locked->opponent_id
            : $locked->challenger_id;

        $winnerPower = $winnerId === $challenger->id ? $challengerPower : $opponentPower;
        $loserPower = $winnerId === $challenger->id ? $opponentPower : $challengerPower;
        $upset = $this->upsetBonus($winnerPower, $loserPower);
        $gloryWinner = Duel::GLORY_WIN + $upset;
        $gloryLoser = Duel::GLORY_LOSS;

        $result['upset_bonus'] = $upset;
        $result['base_powers'] = [
            'challenger' => $challengerPower,
            'opponent' => $opponentPower,
        ];

        $locked->update([
            'status' => Duel::STATUS_RESOLVED,
            'seed' => $seed,
            'log' => $result,
            'winner_id' => $winnerId,
            'glory_winner' => $gloryWinner,
            'glory_loser' => $gloryLoser,
            'resolved_at' => now(),
        ]);

        $this->awardGlory($class, $winnerId, $gloryWinner, won: true);
        $this->awardGlory($class, $loserId, $gloryLoser, won: false);

        $winner = User::query()->findOrFail($winnerId);
        $winnerLabel = $winner->arenaName() ?: $winner->name;
        $gloryLabel = GameCurrency::label('glory');
        $upsetNote = $upset > 0
            ? " (zebra +{$upset}!)"
            : '';

        $locked->challenger->notify(new GameAlert(
            'duel_result',
            'Duelo resolvido',
            $winnerId === $locked->challenger_id
                ? "Você venceu o duelo e ganhou {$gloryWinner} de {$gloryLabel}!{$upsetNote}"
                : "{$winnerLabel} venceu o duelo. Você ganhou {$gloryLoser} de {$gloryLabel}.",
            [
                'duel_id' => $locked->id,
                'class_id' => $class->id,
                'url' => ArenaUrl::route('student.arena.show', $locked).'?replay=1',
            ],
        ));

        $opponent->notify(new GameAlert(
            'duel_result',
            'Duelo resolvido',
            $winnerId === $opponent->id
                ? "Você venceu o duelo e ganhou {$gloryWinner} de {$gloryLabel}!{$upsetNote}"
                : "{$winnerLabel} venceu o duelo. Você ganhou {$gloryLoser} de {$gloryLabel}.",
            [
                'duel_id' => $locked->id,
                'class_id' => $class->id,
                'url' => ArenaUrl::route('student.arena.show', $locked).'?replay=1',
            ],
        ));

        return $locked->fresh(['challenger', 'opponent', 'winner', 'arrangedBy']);
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
        $enrollment->relics = (int) $enrollment->relics + $amount;

        if ($won) {
            $enrollment->arena_wins = (int) $enrollment->arena_wins + 1;
        } else {
            $enrollment->arena_losses = (int) $enrollment->arena_losses + 1;
        }

        $enrollment->save();
    }

    private function debitCurrencies(SchoolClass $class, int $studentId, int $glory, int $relics): void
    {
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            throw new RuntimeException('Matrícula não encontrada para debitar moedas da arena.');
        }

        $enrollment->glory = max(0, (int) $enrollment->glory - $glory);
        $enrollment->relics = max(0, (int) $enrollment->relics - $relics);
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

    private function dailyLimitReason(SchoolClass $class, User $student, ?string $subject = null): ?string
    {
        $limit = $class->arenaDailyLimit();

        if ($this->resolvedTodayCount($class, $student) >= $limit) {
            $label = $limit === 1 ? 'duelo' : 'duelos';
            $who = $subject ?? 'Você';

            return "{$who} já fez {$limit} {$label} hoje. Só pode duelar de novo amanhã.";
        }

        return null;
    }

    private function cooldownReason(SchoolClass $class, User $challenger): ?string
    {
        $minutes = $class->arenaCooldownMinutes();

        if ($minutes <= 0) {
            return null;
        }

        $since = now()->subMinutes($minutes);

        $recent = Duel::query()
            ->where('class_id', $class->id)
            ->where('challenger_id', $challenger->id)
            ->where('created_at', '>=', $since)
            ->whereIn('status', [Duel::STATUS_PENDING, Duel::STATUS_RESOLVED])
            ->exists();

        if ($recent) {
            return 'Aguarde '.$class->arenaCooldownLabel().' entre um desafio e outro.';
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
