<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\ArenaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RealmDuelService
{
    public function __construct(
        private ArenaCombatService $combat,
    ) {}

    /**
     * @throws ValidationException
     */
    public function challenge(SchoolClass $challengerClass, User $challenger, User $opponent): RealmDuel
    {
        $area = $this->requireArea($challengerClass);
        $opponentClass = $this->resolveOpponentClass($area, $challengerClass, $opponent);

        $this->assertArenaOpen($challengerClass);
        $this->assertArenaOpen($opponentClass);
        $this->assertCanFight($challengerClass, $challenger);
        $this->assertCanFight($opponentClass, $opponent);

        if ($challenger->id === $opponent->id) {
            throw ValidationException::withMessages([
                'opponent_id' => 'Você não pode desafiar a si mesmo.',
            ]);
        }

        if ($reason = $this->challengeRestriction($area, $challenger, $opponent)) {
            throw ValidationException::withMessages([
                'opponent_id' => $reason,
            ]);
        }

        $duel = RealmDuel::query()->create([
            'area_id' => $area->id,
            'challenger_class_id' => $challengerClass->id,
            'opponent_class_id' => $opponentClass->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'status' => RealmDuel::STATUS_PENDING,
        ]);

        $challengerLabel = $challenger->arenaName() ?: $challenger->name;

        $opponent->notify(new GameAlert(
            'realm_duel_challenge',
            'Desafio entre turmas',
            "{$challengerLabel} ({$challengerClass->name}) te desafiou por Aura.",
            [
                'realm_duel_id' => $duel->id,
                'area_id' => $area->id,
                'challenger_id' => $challenger->id,
                'challenger_name' => $challenger->name,
                'challenger_arena' => $challenger->arenaName(),
                'challenger_class' => $challengerClass->name,
                'accept_url' => ArenaUrl::route('student.arena.realm.accept', $duel),
                'decline_url' => ArenaUrl::route('student.arena.realm.decline', $duel),
                'url' => ArenaUrl::route('student.arena.index'),
            ],
        ));

        return $duel;
    }

    /**
     * @throws ValidationException
     */
    public function accept(RealmDuel $duel, User $opponent): RealmDuel
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

        $challengerClass = $duel->challengerClass;
        $opponentClass = $duel->opponentClass;
        $this->assertArenaOpen($challengerClass);
        $this->assertArenaOpen($opponentClass);
        $this->assertCanFight($challengerClass, $duel->challenger);
        $this->assertCanFight($opponentClass, $opponent);
        $this->assertDailyResolvedLimit($duel->area, $duel->challenger);
        $this->assertDailyResolvedLimit($duel->area, $opponent);

        return DB::transaction(function () use ($duel, $opponent, $challengerClass, $opponentClass) {
            /** @var RealmDuel $locked */
            $locked = RealmDuel::query()->whereKey($duel->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'duel' => 'Este desafio não está mais pendente.',
                ]);
            }

            $seed = random_int(1, PHP_INT_MAX);
            $result = $this->combat->resolve(
                $locked->challenger,
                $opponent,
                $challengerClass,
                $seed,
                null,
                $opponentClass,
            );
            $winnerId = $result['winner_id'];
            $loserId = $winnerId === $locked->challenger_id
                ? $locked->opponent_id
                : $locked->challenger_id;

            $locked->update([
                'status' => RealmDuel::STATUS_RESOLVED,
                'seed' => $seed,
                'log' => $result,
                'winner_id' => $winnerId,
                'aura_winner' => RealmDuel::AURA_WIN,
                'aura_loser' => RealmDuel::AURA_LOSS,
                'resolved_at' => now(),
            ]);

            $this->awardAura($locked->area, $winnerId, RealmDuel::AURA_WIN);
            $this->awardAura($locked->area, $loserId, RealmDuel::AURA_LOSS);

            $winner = User::query()->findOrFail($winnerId);
            $winnerLabel = $winner->arenaName() ?: $winner->name;

            $locked->challenger->notify(new GameAlert(
                'realm_duel_result',
                'Duelo do reino resolvido',
                $winnerId === $locked->challenger_id
                    ? 'Você venceu e ganhou '.RealmDuel::AURA_WIN.' de Aura!'
                    : "{$winnerLabel} venceu. Você ganhou ".RealmDuel::AURA_LOSS.' de Aura.',
                [
                    'realm_duel_id' => $locked->id,
                    'area_id' => $locked->area_id,
                    'url' => ArenaUrl::route('student.arena.realm.show', $locked).'?replay=1',
                ],
            ));

            $opponent->notify(new GameAlert(
                'realm_duel_result',
                'Duelo do reino resolvido',
                $winnerId === $opponent->id
                    ? 'Você venceu e ganhou '.RealmDuel::AURA_WIN.' de Aura!'
                    : "{$winnerLabel} venceu. Você ganhou ".RealmDuel::AURA_LOSS.' de Aura.',
                [
                    'realm_duel_id' => $locked->id,
                    'area_id' => $locked->area_id,
                    'url' => ArenaUrl::route('student.arena.realm.show', $locked).'?replay=1',
                ],
            ));

            return $locked->fresh([
                'challenger',
                'opponent',
                'winner',
                'challengerClass',
                'opponentClass',
                'area',
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function decline(RealmDuel $duel, User $opponent): RealmDuel
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
            'status' => RealmDuel::STATUS_DECLINED,
            'resolved_at' => now(),
        ]);

        $opponentLabel = $opponent->arenaName() ?: $opponent->name;

        $duel->challenger->notify(new GameAlert(
            'realm_duel_declined',
            'Desafio do reino recusado',
            "{$opponentLabel} recusou o duelo por Aura. Sem punição.",
            [
                'realm_duel_id' => $duel->id,
                'area_id' => $duel->area_id,
                'url' => ArenaUrl::route('student.arena.index'),
            ],
        ));

        return $duel->fresh(['challenger', 'opponent', 'challengerClass', 'opponentClass']);
    }

    /**
     * Cancela um desafio pendente (mediação de staff). Sem premiação.
     *
     * @throws ValidationException
     */
    public function staffCancel(RealmDuel $duel): RealmDuel
    {
        if (! $duel->isPending()) {
            throw ValidationException::withMessages([
                'duel' => 'Só é possível cancelar desafios pendentes.',
            ]);
        }

        $duel->update([
            'status' => RealmDuel::STATUS_DECLINED,
            'resolved_at' => now(),
        ]);

        $payload = [
            'realm_duel_id' => $duel->id,
            'area_id' => $duel->area_id,
            'url' => ArenaUrl::route('student.arena.index'),
        ];

        $duel->challenger->notify(new GameAlert(
            'realm_duel_declined',
            'Desafio do reino cancelado',
            'Um mediador cancelou o duelo por Aura. Sem punição.',
            $payload,
        ));

        $duel->opponent->notify(new GameAlert(
            'realm_duel_declined',
            'Desafio do reino cancelado',
            'Um mediador cancelou o duelo por Aura. Sem punição.',
            $payload,
        ));

        return $duel->fresh(['challenger', 'opponent', 'challengerClass', 'opponentClass']);
    }

    public function challengeRestriction(Area $area, User $challenger, User $opponent): ?string
    {
        return $this->pendingBetweenReason($area, $challenger, $opponent)
            ?? $this->duplicateTodayReason($area, $challenger, $opponent)
            ?? $this->dailyLimitReason($area, $challenger);
    }

    /**
     * @param  iterable<int, User>  $opponents
     * @return array<int, string>
     */
    public function challengeNotices(Area $area, User $challenger, iterable $opponents): array
    {
        $notices = [];

        foreach ($opponents as $opponent) {
            $reason = $this->challengeRestriction($area, $challenger, $opponent);
            if ($reason !== null) {
                $notices[$opponent->id] = $reason;
            }
        }

        return $notices;
    }

    public function resolvedTodayCount(Area $area, User $student): int
    {
        return RealmDuel::query()
            ->where('area_id', $area->id)
            ->where('status', RealmDuel::STATUS_RESOLVED)
            ->where(function ($query) use ($student) {
                $query->where('challenger_id', $student->id)
                    ->orWhere('opponent_id', $student->id);
            })
            ->whereDate('resolved_at', Carbon::today())
            ->count();
    }

    public function auraBalance(Area $area, User $student): int
    {
        return (int) AreaBalance::forStudent($area, $student)->auras;
    }

    /**
     * Alunos de outras turmas do mesmo reino, com persona aprovada.
     *
     * @return Collection<int, array{student: User, class: SchoolClass}>
     */
    public function realmOpponents(SchoolClass $class, User $student): Collection
    {
        $area = $class->area;
        if (! $area) {
            return collect();
        }

        $rows = collect();

        $peerClasses = SchoolClass::query()
            ->where('area_id', $area->id)
            ->where('id', '!=', $class->id)
            ->with(['students' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        foreach ($peerClasses as $peerClass) {
            foreach ($peerClass->students as $peer) {
                if ($peer->id === $student->id) {
                    continue;
                }

                if (! $peer->hasApprovedPersona() || ! $peer->hasCharacterClass()) {
                    continue;
                }

                $rows->push([
                    'student' => $peer,
                    'class' => $peerClass,
                ]);
            }
        }

        return $rows->values();
    }

    private function awardAura(Area $area, int $studentId, int $amount): void
    {
        $balance = AreaBalance::query()
            ->where('area_id', $area->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = AreaBalance::query()->create([
                'area_id' => $area->id,
                'student_id' => $studentId,
                'auras' => 0,
            ]);
            $balance = AreaBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        $balance->auras = (int) $balance->auras + $amount;
        $balance->save();
    }

    private function requireArea(SchoolClass $class): Area
    {
        $area = $class->area;
        if (! $area) {
            throw ValidationException::withMessages([
                'arena' => 'Esta turma não pertence a um reino.',
            ]);
        }

        return $area;
    }

    private function resolveOpponentClass(Area $area, SchoolClass $challengerClass, User $opponent): SchoolClass
    {
        $opponentClass = $opponent->classes()
            ->where('classes.area_id', $area->id)
            ->where('classes.id', '!=', $challengerClass->id)
            ->orderBy('name')
            ->first();

        if (! $opponentClass) {
            throw ValidationException::withMessages([
                'opponent_id' => 'O oponente precisa estar em outra turma deste reino.',
            ]);
        }

        return $opponentClass;
    }

    private function assertArenaOpen(SchoolClass $class): void
    {
        if (! $class->isArenaOpen()) {
            throw ValidationException::withMessages([
                'arena' => "A arena da turma {$class->name} está fechada.",
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

    private function assertDailyResolvedLimit(Area $area, User $student): void
    {
        if ($reason = $this->dailyLimitReason($area, $student)) {
            throw ValidationException::withMessages([
                'opponent_id' => $reason,
            ]);
        }
    }

    private function dailyLimitReason(Area $area, User $student): ?string
    {
        $limit = RealmDuel::DAILY_RESOLVED_LIMIT;

        if ($this->resolvedTodayCount($area, $student) >= $limit) {
            return "Você já fez {$limit} duelos do reino hoje. Só pode de novo amanhã.";
        }

        return null;
    }

    private function duplicateTodayReason(Area $area, User $challenger, User $opponent): ?string
    {
        $exists = RealmDuel::query()
            ->where('area_id', $area->id)
            ->whereDate('created_at', Carbon::today())
            ->where(function ($query) use ($challenger, $opponent) {
                $query->where(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $challenger->id)->where('opponent_id', $opponent->id);
                })->orWhere(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $opponent->id)->where('opponent_id', $challenger->id);
                });
            })
            ->whereIn('status', [RealmDuel::STATUS_PENDING, RealmDuel::STATUS_RESOLVED])
            ->exists();

        if ($exists) {
            $label = $opponent->arenaName() ?: $opponent->name;

            return "Você já desafiou {$label} no reino hoje. Só pode de novo amanhã.";
        }

        return null;
    }

    private function pendingBetweenReason(Area $area, User $challenger, User $opponent): ?string
    {
        $exists = RealmDuel::query()
            ->where('area_id', $area->id)
            ->where('status', RealmDuel::STATUS_PENDING)
            ->where(function ($query) use ($challenger, $opponent) {
                $query->where(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $challenger->id)->where('opponent_id', $opponent->id);
                })->orWhere(function ($inner) use ($challenger, $opponent) {
                    $inner->where('challenger_id', $opponent->id)->where('opponent_id', $challenger->id);
                });
            })
            ->exists();

        if ($exists) {
            $label = $opponent->arenaName() ?: $opponent->name;

            return "Já existe um desafio pendente com {$label}. Aguarde a resposta.";
        }

        return null;
    }
}
