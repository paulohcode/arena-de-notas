<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use App\Support\SeededRandom;
use InvalidArgumentException;

class ArenaCombatService
{
    public const MAX_TURNS = 12;

    /**
     * Multiplicadores de poder por nível acadêmico.
     *
     * @var array<string, float>
     */
    private const LEVEL_POWER = [
        'iniciante' => 1.0,
        'aprendiz' => 1.12,
        'pleno' => 1.25,
        'expert' => 1.4,
        'mestre' => 1.55,
    ];

    public function __construct(private GradeCalculator $grades) {}

    /**
     * Resolve um duelo de forma determinística a partir da semente.
     *
     * @return array{
     *     winner_id: int,
     *     turns: list<array{turn: int, actor_id: int, action: string, amount: int, actor_hp: int, target_hp: int, text: string}>,
     *     fighters: array{challenger: array<string, mixed>, opponent: array<string, mixed>}
     * }
     */
    public function resolve(User $challenger, User $opponent, SchoolClass $class, int $seed): array
    {
        $challengerFighter = $this->buildFighter($challenger, $class);
        $opponentFighter = $this->buildFighter($opponent, $class);

        $rng = new SeededRandom($seed);
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

        $winnerId = $this->decideWinner($fighters, $challenger->id, $opponent->id);

        return [
            'winner_id' => $winnerId,
            'turns' => $turns,
            'fighters' => [
                'challenger' => $this->publicFighterSnapshot($challengerFighter, $fighters['challenger']['hp']),
                'opponent' => $this->publicFighterSnapshot($opponentFighter, $fighters['opponent']['hp']),
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, arena_name: ?string, class: string, max_hp: int, hp: int, atk: int, def: int, spd: int, heal_chance: float, power: float}
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
        $average = $this->grades->studentAverage($student, $class);
        $power = $this->powerMultiplier($level['key'], $average);

        $maxHp = max(1, (int) round($meta['hp'] * $power));
        $atk = max(1, (int) round($meta['atk'] * $power));
        $def = max(0, (int) round($meta['def'] * $power));
        $spd = max(1, (int) round($meta['spd'] * $power));

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
            'heal_chance' => (float) $meta['heal_chance'],
            'power' => round($power, 3),
        ];
    }

    private function powerMultiplier(string $levelKey, float $average): float
    {
        $levelPower = self::LEVEL_POWER[$levelKey] ?? 1.0;
        $gradeBonus = 1 + (max(0, min(100, $average)) / 100) * 0.3;

        return round($levelPower * $gradeBonus, 4);
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
     * @param  array{challenger: array{id: int, hp: int}, opponent: array{id: int, hp: int}}  $fighters
     */
    private function decideWinner(array $fighters, int $challengerId, int $opponentId): int
    {
        if ($fighters['challenger']['hp'] <= 0 && $fighters['opponent']['hp'] > 0) {
            return $opponentId;
        }

        if ($fighters['opponent']['hp'] <= 0 && $fighters['challenger']['hp'] > 0) {
            return $challengerId;
        }

        if ($fighters['challenger']['hp'] === $fighters['opponent']['hp']) {
            return $fighters['challenger']['spd'] >= $fighters['opponent']['spd']
                ? $challengerId
                : $opponentId;
        }

        return $fighters['challenger']['hp'] > $fighters['opponent']['hp']
            ? $challengerId
            : $opponentId;
    }

    /**
     * @param  array{id: int, name: string, arena_name: ?string, class: string, max_hp: int, atk: int, def: int, spd: int, power: float}  $start
     * @return array{id: int, name: string, arena_name: ?string, class: string, max_hp: int, hp: int, atk: int, def: int, spd: int, power: float}
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
        ];
    }
}
