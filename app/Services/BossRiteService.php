<?php

namespace App\Services;

use App\Models\BossVigil;
use App\Models\Enrollment;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\SeasonClassRite;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\ArenaUrl;
use App\Support\BossArchetypeCatalog;
use App\Support\SeededRandom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BossRiteService
{
    public function __construct(
        private ArenaCombatService $combat,
    ) {}

    /**
     * Abre ou fecha a Vigília da temporada (Sombra solo).
     */
    public function setVigilOpen(Season $season, bool $open): Season
    {
        if ($open && ! $season->hasBoss()) {
            throw ValidationException::withMessages([
                'vigil' => 'Escolha um arquétipo de chefão antes de abrir a Vigília.',
            ]);
        }

        $season->update(['vigil_open' => $open]);

        return $season->fresh();
    }

    /**
     * Contagem de Marcas do Rito da turma na temporada (vitórias na Vigília do aluno).
     */
    public function markCount(Season $season, SchoolClass $class): int
    {
        return BossVigil::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->where('source', BossVigil::SOURCE_STUDENT)
            ->where('mark_earned', true)
            ->count();
    }

    /**
     * Motivo que impede a Vigília, ou null se liberada.
     */
    public function vigilRestriction(Season $season, SchoolClass $class, User $student): ?string
    {
        if (! $season->hasBoss()) {
            return 'Esta temporada ainda não tem um chefão.';
        }

        if (! $season->isVigilOpen()) {
            return 'A Vigília desta temporada está fechada.';
        }

        if (! $season->classes()->where('classes.id', $class->id)->exists()) {
            return 'Sua turma não participa desta temporada.';
        }

        if (! $class->isArenaOpen()) {
            return 'A arena desta turma está fechada.';
        }

        if ($reason = $this->fighterRestriction($class, $student)) {
            return $reason;
        }

        if ($this->resolvedVigilsToday($season, $class, $student) >= BossArchetypeCatalog::VIGIL_DAILY_LIMIT) {
            return 'Você já enfrentou a Sombra hoje. Volte amanhã.';
        }

        if ($this->resolvedVigilsThisWeek($season, $class, $student) >= BossArchetypeCatalog::VIGIL_WEEKLY_LIMIT) {
            return 'Você já usou as '.BossArchetypeCatalog::VIGIL_WEEKLY_LIMIT.' Vigílias da semana.';
        }

        $openRite = SeasonClassRite::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->whereIn('status', [SeasonClassRite::STATUS_OPEN, SeasonClassRite::STATUS_RESOLVED])
            ->exists();

        if ($openRite) {
            return 'O Rito desta turma já foi aberto — a Vigília encerrou.';
        }

        return null;
    }

    /**
     * @throws ValidationException
     */
    public function challengeShadow(Season $season, SchoolClass $class, User $student): BossVigil
    {
        if ($reason = $this->vigilRestriction($season, $class, $student)) {
            throw ValidationException::withMessages(['vigil' => $reason]);
        }

        return DB::transaction(function () use ($season, $class, $student) {
            $seed = random_int(1, PHP_INT_MAX);
            $boss = $this->combat->buildBossFighter(
                (string) $season->boss_archetype,
                (string) $season->boss_difficulty,
                shadow: true,
            );

            $result = $this->combat->resolveAgainstBoss($student, $class, $boss, $seed, luckRange: 0.08);
            $won = $result['winner_id'] === $student->id;
            $glory = $won ? BossArchetypeCatalog::GLORY_WIN : BossArchetypeCatalog::GLORY_LOSS;

            $vigil = BossVigil::query()->create([
                'season_id' => $season->id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'source' => BossVigil::SOURCE_STUDENT,
                'initiated_by' => null,
                'status' => BossVigil::STATUS_RESOLVED,
                'seed' => $seed,
                'log' => $result,
                'won' => $won,
                'mark_earned' => $won,
                'glory' => $glory,
                'resolved_at' => now(),
            ]);

            $this->awardGloryAndRelics($class, $student->id, $glory, $won);

            return $vigil;
        });
    }

    /**
     * Professor/admin, no papel do chefão, desafia um aluno.
     * Resolve na hora (replay cinematográfico). Não gera Marca nem consome limite diário.
     *
     * @throws ValidationException
     */
    public function staffChallenge(Season $season, SchoolClass $class, User $student, User $staff): BossVigil
    {
        if (! $season->hasBoss()) {
            throw ValidationException::withMessages([
                'challenge' => 'Esta temporada ainda não tem um chefão.',
            ]);
        }

        if (! $season->classes()->where('classes.id', $class->id)->exists()) {
            throw ValidationException::withMessages([
                'challenge' => 'Esta turma não participa da temporada.',
            ]);
        }

        if ($reason = $this->fighterRestriction($class, $student)) {
            throw ValidationException::withMessages(['challenge' => $reason]);
        }

        return DB::transaction(function () use ($season, $class, $student, $staff) {
            $seed = random_int(1, PHP_INT_MAX);
            $boss = $this->combat->buildBossFighter(
                (string) $season->boss_archetype,
                (string) $season->boss_difficulty,
                shadow: true,
            );
            $boss['arena_name'] = $boss['name'];

            $result = $this->combat->resolveAgainstBoss($student, $class, $boss, $seed, luckRange: 0.08);
            $studentWon = $result['winner_id'] === $student->id;
            $glory = $studentWon ? BossArchetypeCatalog::GLORY_WIN : BossArchetypeCatalog::GLORY_LOSS;

            $vigil = BossVigil::query()->create([
                'season_id' => $season->id,
                'class_id' => $class->id,
                'student_id' => $student->id,
                'source' => BossVigil::SOURCE_STAFF,
                'initiated_by' => $staff->id,
                'status' => BossVigil::STATUS_RESOLVED,
                'seed' => $seed,
                'log' => $result,
                'won' => $studentWon,
                'mark_earned' => false,
                'glory' => $glory,
                'resolved_at' => now(),
            ]);

            $this->awardGloryAndRelics($class, $student->id, $glory, $studentWon);

            $bossName = $season->bossDisplayName() ?? 'o chefão';
            $student->notify(new GameAlert(
                'season_rite',
                'O chefão te desafiou',
                "{$bossName} te provocou na arena. Assista o combate.",
                [
                    'season_id' => $season->id,
                    'class_id' => $class->id,
                    'vigil_id' => $vigil->id,
                    'url' => ArenaUrl::route('student.arena.vigil.show', $vigil),
                ],
            ));

            return $vigil;
        });
    }

    /**
     * Alunos elegíveis das turmas da temporada, agrupados por turma.
     *
     * @return list<array{class: SchoolClass, students: list<array{user: User, power: float, notice: ?string}>}>
     */
    public function bossDeskRoster(Season $season): array
    {
        $rows = [];

        foreach ($season->classes()->with(['students'])->orderBy('name')->get() as $class) {
            $students = [];
            foreach ($class->students()->orderBy('name')->get() as $student) {
                $notice = $this->fighterRestriction($class, $student);
                $power = 0.0;
                if ($notice === null) {
                    try {
                        $power = (float) $this->combat->buildFighter($student, $class)['power'];
                    } catch (\InvalidArgumentException) {
                        $notice = 'Personagem incompleto para o combate.';
                    }
                }

                $students[] = [
                    'user' => $student,
                    'power' => $power,
                    'notice' => $notice,
                ];
            }

            usort($students, fn ($a, $b) => $b['power'] <=> $a['power']);
            $rows[] = [
                'class' => $class,
                'students' => $students,
            ];
        }

        return $rows;
    }

    public function resolvedVigilsToday(Season $season, SchoolClass $class, User $student): int
    {
        return BossVigil::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->where('source', BossVigil::SOURCE_STUDENT)
            ->whereDate('resolved_at', Carbon::today())
            ->count();
    }

    public function resolvedVigilsThisWeek(Season $season, SchoolClass $class, User $student): int
    {
        return BossVigil::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->where('source', BossVigil::SOURCE_STUDENT)
            ->where('resolved_at', '>=', Carbon::now()->startOfWeek())
            ->count();
    }

    /**
     * Abre o Rito da turma: HP compartilhado já enfraquecido pelas Marcas.
     *
     * @throws ValidationException
     */
    public function openRite(Season $season, SchoolClass $class): SeasonClassRite
    {
        if (! $season->hasBoss()) {
            throw ValidationException::withMessages([
                'rite' => 'Escolha um arquétipo de chefão antes de abrir o Rito.',
            ]);
        }

        if (! $season->classes()->where('classes.id', $class->id)->exists()) {
            throw ValidationException::withMessages([
                'rite' => 'Esta turma não participa da temporada.',
            ]);
        }

        $existing = SeasonClassRite::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->first();

        if ($existing?->isResolved()) {
            throw ValidationException::withMessages([
                'rite' => 'O Rito desta turma já foi resolvido.',
            ]);
        }

        if ($existing?->isOpen()) {
            throw ValidationException::withMessages([
                'rite' => 'O Rito desta turma já está aberto.',
            ]);
        }

        $fighters = $this->eligibleFighters($class);
        if ($fighters->isEmpty()) {
            throw ValidationException::withMessages([
                'rite' => 'É preciso ao menos um aluno com personagem aprovado.',
            ]);
        }

        $marks = $this->markCount($season, $class);
        $baseHp = BossArchetypeCatalog::raidBaseHp($fighters->count());
        $bossHp = BossArchetypeCatalog::weakenedRaidHp($baseHp, $marks);

        return SeasonClassRite::query()->updateOrCreate(
            [
                'season_id' => $season->id,
                'class_id' => $class->id,
            ],
            [
                'status' => SeasonClassRite::STATUS_OPEN,
                'boss_max_hp' => $baseHp,
                'boss_hp' => $bossHp,
                'marks_applied' => $marks,
                'seed' => null,
                'log' => null,
                'outcome' => null,
                'opened_at' => now(),
                'resolved_at' => null,
            ],
        );
    }

    /**
     * Resolve o assalto da turma: série de 1v1 no HP compartilhado do chefão.
     *
     * @throws ValidationException
     */
    public function resolveRite(Season $season, SchoolClass $class): SeasonClassRite
    {
        $rite = SeasonClassRite::query()
            ->where('season_id', $season->id)
            ->where('class_id', $class->id)
            ->first();

        if (! $rite || ! $rite->isOpen()) {
            throw ValidationException::withMessages([
                'rite' => 'Abra o Rito desta turma antes de resolver o assalto.',
            ]);
        }

        $fighters = $this->eligibleFighters($class);
        if ($fighters->isEmpty()) {
            throw ValidationException::withMessages([
                'rite' => 'Nenhum lutador elegível para o Rito.',
            ]);
        }

        return DB::transaction(function () use ($season, $class, $rite, $fighters) {
            /** @var SeasonClassRite $locked */
            $locked = SeasonClassRite::query()->whereKey($rite->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'rite' => 'O Rito não está mais aberto.',
                ]);
            }

            $seed = random_int(1, PHP_INT_MAX);
            $result = $this->resolveRaidSeries($season, $class, $fighters, $locked, $seed);
            $broken = $result['boss_hp_end'] <= 0;
            $outcome = $broken
                ? SeasonClassRite::OUTCOME_BROKEN
                : SeasonClassRite::OUTCOME_RESISTED;

            $locked->update([
                'status' => SeasonClassRite::STATUS_RESOLVED,
                'boss_hp' => $result['boss_hp_end'],
                'seed' => $seed,
                'log' => $result,
                'outcome' => $outcome,
                'resolved_at' => now(),
            ]);

            $relics = $broken
                ? BossArchetypeCatalog::RELICS_RITE_WIN
                : BossArchetypeCatalog::RELICS_RITE_LOSS;

            foreach ($result['fighter_ids'] as $studentId) {
                $this->awardRelicsOnly($class, $studentId, $relics);
            }

            $bossName = $season->bossDisplayName() ?? 'o Rito';
            $title = $broken ? 'Rito quebrado!' : 'O Rito resistiu';
            $body = $broken
                ? "A turma derrubou {$bossName}. +{$relics} ".GameCurrency::label('relics').'.'
                : "{$bossName} aguentou o assalto. +{$relics} ".GameCurrency::label('relics').' pela tentativa.';

            foreach ($fighters as $fighter) {
                $fighter['user']->notify(new GameAlert(
                    'season_rite',
                    $title,
                    $body,
                    [
                        'season_id' => $season->id,
                        'class_id' => $class->id,
                        'rite_id' => $locked->id,
                        'url' => ArenaUrl::route('student.arena.rite.show', $locked),
                    ],
                ));
            }

            return $locked->fresh();
        });
    }

    /**
     * Lutadores elegíveis ordenados por poder (guildas fortes primeiro via poder).
     *
     * @return Collection<int, array{user: User, power: float}>
     */
    public function eligibleFighters(SchoolClass $class): Collection
    {
        $students = $class->students()
            ->whereNotNull('character_class')
            ->get()
            ->filter(fn (User $student) => $student->hasApprovedPersona() && $student->hasCharacterClass())
            ->values();

        return $students
            ->map(function (User $student) use ($class) {
                try {
                    $fighter = $this->combat->buildFighter($student, $class);
                } catch (\InvalidArgumentException) {
                    return null;
                }

                return [
                    'user' => $student,
                    'power' => (float) $fighter['power'],
                ];
            })
            ->filter()
            ->sortByDesc('power')
            ->values();
    }

    /**
     * @param  Collection<int, array{user: User, power: float}>  $fighters
     * @return array<string, mixed>
     */
    private function resolveRaidSeries(
        Season $season,
        SchoolClass $class,
        Collection $fighters,
        SeasonClassRite $rite,
        int $seed,
    ): array {
        $rng = new SeededRandom($seed);
        $bossHp = (int) $rite->boss_hp;
        $bossMaxHp = (int) $rite->boss_max_hp;
        $waves = [];
        $damageBoard = [];
        $fighterIds = [];

        foreach ($fighters as $index => $entry) {
            if ($bossHp <= 0) {
                break;
            }

            /** @var User $student */
            $student = $entry['user'];
            $fightSeed = $rng->nextInt(1, PHP_INT_MAX);
            $boss = $this->combat->buildBossFighter(
                (string) $season->boss_archetype,
                (string) $season->boss_difficulty,
                shadow: false,
                overrideMaxHp: $bossMaxHp,
                overrideHp: $bossHp,
            );

            $result = $this->combat->resolveAgainstBoss(
                $student,
                $class,
                $boss,
                $fightSeed,
                luckRange: 0.06,
            );

            $bossHp = (int) $result['boss_hp_end'];
            $damage = (int) $result['damage_dealt'];
            $fighterIds[] = $student->id;
            $damageBoard[] = [
                'student_id' => $student->id,
                'name' => $student->name,
                'arena_name' => $student->arenaName(),
                'class' => $student->characterClassLabel(),
                'damage' => $damage,
                'survived' => ($result['fighters']['challenger']['hp'] ?? 0) > 0,
            ];

            $waves[] = [
                'index' => $index + 1,
                'seed' => $fightSeed,
                'challenger_id' => $student->id,
                'winner_id' => $result['winner_id'],
                'winner_reason' => $result['winner_reason'],
                'damage_dealt' => $damage,
                'boss_hp_start' => $result['boss_hp_start'],
                'boss_hp_end' => $result['boss_hp_end'],
                'turns' => $result['turns'],
                'fighters' => $result['fighters'],
            ];
        }

        usort($damageBoard, fn ($a, $b) => $b['damage'] <=> $a['damage']);

        return [
            'outcome' => $bossHp <= 0
                ? SeasonClassRite::OUTCOME_BROKEN
                : SeasonClassRite::OUTCOME_RESISTED,
            'boss_max_hp' => $bossMaxHp,
            'boss_hp_start' => (int) $rite->boss_hp,
            'boss_hp_end' => max(0, $bossHp),
            'marks_applied' => (int) $rite->marks_applied,
            'fighter_ids' => $fighterIds,
            'damage_board' => $damageBoard,
            'waves' => $waves,
            'archetype' => $season->boss_archetype,
            'boss_name' => $season->bossDisplayName(),
            'boss_meta' => $season->bossMeta(),
        ];
    }

    private function fighterRestriction(SchoolClass $class, User $student): ?string
    {
        if (! $student->isStudent()) {
            return 'Apenas alunos podem enfrentar o chefão.';
        }

        if (! $student->enrollmentIn($class)) {
            return 'Você precisa estar matriculado nesta turma.';
        }

        if (! $student->hasCharacterClass()) {
            return 'É preciso escolher uma classe de personagem.';
        }

        if (! $student->hasApprovedPersona()) {
            return 'Avatar e nome de jogo precisam estar aprovados.';
        }

        return null;
    }

    private function awardGloryAndRelics(SchoolClass $class, int $studentId, int $amount, bool $won): void
    {
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            throw new RuntimeException('Matrícula não encontrada para premiar a Vigília.');
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

    private function awardRelicsOnly(SchoolClass $class, int $studentId, int $amount): void
    {
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            throw new RuntimeException('Matrícula não encontrada para premiar o Rito.');
        }

        $enrollment->relics = (int) $enrollment->relics + $amount;
        $enrollment->save();
    }
}
