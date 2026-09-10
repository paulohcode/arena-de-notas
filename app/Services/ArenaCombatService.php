<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\CosmeticCatalog;
use App\Support\SeededRandom;
use InvalidArgumentException;

class ArenaCombatService
{
    public const MAX_TURNS = 12;

    /**
     * Peso máximo da média acadêmica no poder (provas, comportamento).
     */
    public const GRADE_WEIGHT = 0.30;

    /**
     * Peso máximo da frequência no poder. Não entra na média da prova.
     */
    public const ATTENDANCE_WEIGHT = 0.08;

    /**
     * Peso máximo da nota da guilda no poder. Não entra na média da prova.
     */
    public const TEAM_WEIGHT = 0.10;

    /**
     * Variação oculta de poder em cada duelo. Impede que o resultado nasça decidido.
     */
    public const LUCK_RANGE = 0.12;

    /**
     * Atributos base iguais para todos os lutadores. A classe de personagem é só visual.
     */
    public const BASE_HP = 100;

    public const BASE_ATK = 18;

    public const BASE_DEF = 12;

    public const BASE_SPD = 10;

    public const BASE_HEAL_CHANCE = 0.10;

    /**
     * Multiplicadores de poder por nível acadêmico (XP).
     *
     * @var array<string, float>
     */
    public const LEVEL_POWER = [
        'iniciante' => 1.0,
        'aprendiz' => 1.12,
        'pleno' => 1.25,
        'expert' => 1.4,
        'mestre' => 1.55,
    ];

    public function __construct(private GradeCalculator $grades) {}

    /**
     * @return array{
     *     max_turns: int,
     *     grade_weight: float,
     *     attendance_weight: float,
     *     team_weight: float,
     *     luck_range: float,
     *     gear_cap: float,
     *     level_power: array<string, float>,
     *     gear_by_rarity: array<string, float>,
     *     base_hp: int,
     *     base_atk: int,
     *     base_def: int,
     *     base_spd: int
     * }
     */
    public static function rulebook(): array
    {
        return [
            'max_turns' => self::MAX_TURNS,
            'grade_weight' => self::GRADE_WEIGHT,
            'attendance_weight' => self::ATTENDANCE_WEIGHT,
            'team_weight' => self::TEAM_WEIGHT,
            'luck_range' => self::LUCK_RANGE,
            'gear_cap' => CosmeticCatalog::COMBAT_BONUS_CAP,
            'level_power' => self::LEVEL_POWER,
            'gear_by_rarity' => CosmeticCatalog::COMBAT_BONUS_BY_RARITY,
            'base_hp' => self::BASE_HP,
            'base_atk' => self::BASE_ATK,
            'base_def' => self::BASE_DEF,
            'base_spd' => self::BASE_SPD,
        ];
    }

    /**
     * Resolve um duelo de forma determinística a partir da semente.
     *
     * @return array{
     *     winner_id: int,
     *     winner_reason: string,
     *     turns: list<array{turn: int, actor_id: int, action: string, amount: int, actor_hp: int, target_hp: int, text: string}>,
     *     fighters: array{challenger: array<string, mixed>, opponent: array<string, mixed>}
     * }
     */
    public function resolve(
        User $challenger,
        User $opponent,
        SchoolClass $class,
        int $seed,
        ?float $luckRange = null,
        ?SchoolClass $opponentClass = null,
    ): array {
        $challengerFighter = $this->buildFighter($challenger, $class);
        $opponentFighter = $this->buildFighter($opponent, $opponentClass ?? $class);

        $rng = new SeededRandom($seed);
        $luck = $luckRange ?? self::LUCK_RANGE;
        $challengerFighter = $this->applyArenaFortune($challengerFighter, $rng, $luck);
        $opponentFighter = $this->applyArenaFortune($opponentFighter, $rng, $luck);
        $turns = [];

        $order = $challengerFighter['spd'] >= $opponentFighter['spd']
            ? ['challenger', 'opponent']
            : ['opponent', 'challenger'];

        // Empate de SPD: a semente decide quem começa.
        if ($challengerFighter['spd'] === $opponentFighter['spd'] && $rng->nextFloat() < 0.5) {
            $order = ['opponent', 'challenger'];
        }

        $fighters = [
            'challenger' => $challengerFighter,
            'opponent' => $opponentFighter,
        ];

        for ($turn = 1; $turn <= self::MAX_TURNS; $turn++) {
            foreach ($order as $side) {
                $actor = &$fighters[$side];
                $targetSide = $side === 'challenger' ? 'opponent' : 'challenger';
                $target = &$fighters[$targetSide];

                if ($actor['hp'] <= 0 || $target['hp'] <= 0) {
                    break 2;
                }

                $turnLog = $this->act($rng, $turn, $actor, $target);
                $turns[] = $turnLog;

                unset($actor, $target);

                if ($fighters['challenger']['hp'] <= 0 || $fighters['opponent']['hp'] <= 0) {
                    break 2;
                }
            }
        }

        [$winnerId, $winnerReason] = $this->decideWinner($fighters, $challenger->id, $opponent->id);

        return [
            'winner_id' => $winnerId,
            'winner_reason' => $winnerReason,
            'turns' => $turns,
            'fighters' => [
                'challenger' => $this->publicFighterSnapshot($challengerFighter, $fighters['challenger']['hp']),
                'opponent' => $this->publicFighterSnapshot($opponentFighter, $fighters['opponent']['hp']),
            ],
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     arena_name: ?string,
     *     class: string,
     *     max_hp: int,
     *     hp: int,
     *     atk: int,
     *     def: int,
     *     spd: int,
     *     heal_chance: float,
     *     power: float,
     *     breakdown: array<string, mixed>
     * }
     */
    public function buildFighter(User $student, SchoolClass $class): array
    {
        $meta = $student->characterClassMeta();
        if ($meta === null) {
            throw new InvalidArgumentException('Aluno sem classe de personagem.');
        }

        $enrollment = $student->enrollmentIn($class);
        $xp = (int) ($enrollment?->xp ?? 0);
        $level = $this->grades->levelFromXp($xp);
        $academic = $this->grades->combatAcademicAverage($student, $class);
        $attendanceScore = $this->grades->hasGradedAttendance($class)
            ? $this->grades->attendanceScore($student, $class)
            : 0.0;
        $teamScore = $this->grades->combatTeamScore($student, $class);
        $gear = CosmeticCatalog::equippedCombatBonus($enrollment ?? new Enrollment);
        $power = $this->powerMultiplier($level['key'], $academic, $attendanceScore, $teamScore, $gear['bonus']);

        $maxHp = max(1, (int) round(self::BASE_HP * $power));
        $atk = max(1, (int) round(self::BASE_ATK * $power));
        $def = max(0, (int) round(self::BASE_DEF * $power));
        $spd = max(1, (int) round(self::BASE_SPD * $power));

        $gradeBonus = $this->gradeBonus($academic);
        $attendanceBonus = $this->attendanceBonus($attendanceScore);
        $teamBonus = $this->teamBonus($teamScore);

        return [
            'id' => $student->id,
            'name' => $student->name,
            'arena_name' => $student->arenaName(),
            'class' => $meta['name'],
            'max_hp' => $maxHp,
            'hp' => $maxHp,
            'atk' => $atk,
            'def' => $def,
            'spd' => $spd,
            'heal_chance' => self::BASE_HEAL_CHANCE,
            'power' => round($power, 3),
            'breakdown' => [
                'level_name' => $level['name'],
                'level_mult' => self::LEVEL_POWER[$level['key']] ?? 1.0,
                'grade' => $academic,
                'grade_bonus' => $gradeBonus,
                'attendance' => $attendanceScore,
                'attendance_bonus' => $attendanceBonus,
                'team' => $teamScore,
                'team_bonus' => $teamBonus,
                'gear_bonus' => $gear['bonus'],
                'gear_items' => $gear['items'],
            ],
        ];
    }

    private function powerMultiplier(string $levelKey, float $academic, float $attendanceScore, float $teamScore, float $gearBonus): float
    {
        $levelPower = self::LEVEL_POWER[$levelKey] ?? 1.0;

        return round(
            $levelPower * (1 + $this->gradeBonus($academic) + $this->attendanceBonus($attendanceScore) + $this->teamBonus($teamScore) + $gearBonus),
            4,
        );
    }

    private function gradeBonus(float $academic): float
    {
        return (max(0, min(100, $academic)) / 100) * self::GRADE_WEIGHT;
    }

    private function attendanceBonus(float $attendanceScore): float
    {
        return (max(0, min(100, $attendanceScore)) / 100) * self::ATTENDANCE_WEIGHT;
    }

    private function teamBonus(float $teamScore): float
    {
        return (max(0, min(100, $teamScore)) / 100) * self::TEAM_WEIGHT;
    }

    /**
     * @param  array<string, mixed>  $fighter
     * @return array<string, mixed>
     */
    private function applyArenaFortune(array $fighter, SeededRandom $rng, float $luckRange = self::LUCK_RANGE): array
    {
        $range = max(0, min(0.5, $luckRange));
        $luck = round(($rng->nextFloat() * 2 - 1) * $range, 4);
        $factor = 1 + $luck;

        $fighter['max_hp'] = max(1, (int) round($fighter['max_hp'] * $factor));
        $fighter['hp'] = $fighter['max_hp'];
        $fighter['atk'] = max(1, (int) round($fighter['atk'] * $factor));
        $fighter['def'] = max(0, (int) round($fighter['def'] * $factor));
        $fighter['spd'] = max(1, (int) round($fighter['spd'] * $factor));
        $fighter['power'] = round($fighter['power'] * $factor, 3);
        $fighter['breakdown']['luck'] = $luck;

        return $fighter;
    }

    /**
     * @param  array{id: int, name: string, arena_name: ?string, class: string, max_hp: int, hp: int, atk: int, def: int, spd: int, heal_chance: float, power: float}  $actor
     * @param  array{id: int, name: string, arena_name: ?string, class: string, max_hp: int, hp: int, atk: int, def: int, spd: int, heal_chance: float, power: float}  $target
     * @return array{turn: int, actor_id: int, action: string, amount: int, actor_hp: int, target_hp: int, text: string}
     */
    private function act(SeededRandom $rng, int $turn, array &$actor, array &$target): array
    {
        $label = $actor['arena_name'] ?: $actor['name'];
        $targetLabel = $target['arena_name'] ?: $target['name'];

        if ($actor['heal_chance'] > 0 && $actor['hp'] < $actor['max_hp'] && $rng->nextFloat() < $actor['heal_chance']) {
            $heal = max(1, (int) round($actor['atk'] * (0.45 + $rng->nextFloat() * 0.35)));
            $actor['hp'] = min($actor['max_hp'], $actor['hp'] + $heal);

            return [
                'turn' => $turn,
                'actor_id' => $actor['id'],
                'action' => 'heal',
                'amount' => $heal,
                'actor_hp' => $actor['hp'],
                'target_hp' => $target['hp'],
                'text' => "{$label} recupera {$heal} de vida.",
            ];
        }

        $base = max(1, $actor['atk'] - (int) floor($target['def'] / 2));
        $variance = 0.8 + ($rng->nextFloat() * 0.4);
        $damage = max(1, (int) round($base * $variance));
        $target['hp'] = max(0, $target['hp'] - $damage);

        return [
            'turn' => $turn,
            'actor_id' => $actor['id'],
            'action' => 'attack',
            'amount' => $damage,
            'actor_hp' => $actor['hp'],
            'target_hp' => $target['hp'],
            'text' => "{$label} golpeia {$targetLabel} por {$damage} de dano.",
        ];
    }

    /**
     * @param  array{challenger: array{id: int, hp: int, spd: int}, opponent: array{id: int, hp: int, spd: int}}  $fighters
     * @return array{0: int, 1: string}
     */
    private function decideWinner(array $fighters, int $challengerId, int $opponentId): array
    {
        if ($fighters['challenger']['hp'] <= 0 && $fighters['opponent']['hp'] > 0) {
            return [$opponentId, 'ko'];
        }

        if ($fighters['opponent']['hp'] <= 0 && $fighters['challenger']['hp'] > 0) {
            return [$challengerId, 'ko'];
        }

        if ($fighters['challenger']['hp'] === $fighters['opponent']['hp']) {
            if ($fighters['challenger']['spd'] === $fighters['opponent']['spd']) {
                return [$challengerId, 'spd_tie'];
            }

            return $fighters['challenger']['spd'] > $fighters['opponent']['spd']
                ? [$challengerId, 'spd']
                : [$opponentId, 'spd'];
        }

        return $fighters['challenger']['hp'] > $fighters['opponent']['hp']
            ? [$challengerId, 'hp']
            : [$opponentId, 'hp'];
    }

    /**
     * @param  array<string, mixed>  $start
     * @return array<string, mixed>
     */
    private function publicFighterSnapshot(array $start, int $finalHp): array
    {
        return [
            'id' => $start['id'],
            'name' => $start['name'],
            'arena_name' => $start['arena_name'],
            'class' => $start['class'],
            'max_hp' => $start['max_hp'],
            'hp' => max(0, $finalHp),
            'atk' => $start['atk'],
            'def' => $start['def'],
            'spd' => $start['spd'],
            'power' => $start['power'],
            'breakdown' => $start['breakdown'],
        ];
    }
}
