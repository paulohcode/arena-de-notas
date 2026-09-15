<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class BossArchetypeCatalog
{
    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_NORMAL = 'normal';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTY_ELITE = 'elite';

    /**
     * Multiplicador de poder fixo do chefão (não escala no aluno).
     *
     * @var array<string, float>
     */
    public const DIFFICULTY_POWER = [
        self::DIFFICULTY_EASY => 1.05,
        self::DIFFICULTY_NORMAL => 1.22,
        self::DIFFICULTY_HARD => 1.42,
        self::DIFFICULTY_ELITE => 1.65,
    ];

    /**
     * @var array<string, string>
     */
    public const DIFFICULTY_LABELS = [
        self::DIFFICULTY_EASY => 'Fácil',
        self::DIFFICULTY_NORMAL => 'Normal',
        self::DIFFICULTY_HARD => 'Difícil',
        self::DIFFICULTY_ELITE => 'Elite',
    ];

    /** Fração do HP de raid por lutador elegível da turma. */
    public const RAID_HP_PER_FIGHTER = 95;

    /** HP mínimo do rito, mesmo com turma pequena. */
    public const RAID_HP_FLOOR = 280;

    /** Multiplicador de HP da Sombra em relação ao chefão base. */
    public const SHADOW_HP_MULT = 0.55;

    /** Cada Marca do Rito reduz o HP inicial do assalto. */
    public const MARK_WEAKEN_PCT = 0.04;

    /** Teto de enfraquecimento por Marcas (~40%). */
    public const MARK_WEAKEN_CAP = 0.40;

    /** Tentativas de Vigília resolvidas por aluno por dia. */
    public const VIGIL_DAILY_LIMIT = 1;

    /** Teto semanal de Vigílias por aluno na temporada. */
    public const VIGIL_WEEKLY_LIMIT = 3;

    /** Taxa em Relíquias para desafiar o chefão completo. */
    public const BOSS_CHALLENGE_FEE = 10;

    /** Limite diário padrão de desafios pagos ao chefão (por dia da semana). */
    public const BOSS_CHALLENGE_DAILY_DEFAULT = 1;

    /** Teto configurável de desafios pagos por dia. */
    public const BOSS_CHALLENGE_DAILY_MAX = 20;

    /** Pote mínimo de moedas no loot de vitória do chefão. */
    public const BOSS_CHALLENGE_LOOT_FLOOR = 6;

    /** Faixa extra de moedas no loot (score 0–1 → +0..12). */
    public const BOSS_CHALLENGE_LOOT_SPAN = 12;

    /** Pote mínimo na derrota: 1 de cada moeda. */
    public const BOSS_CHALLENGE_LOSS_LOOT_FLOOR = 3;

    /** Faixa extra aleatória na derrota (+0..3). */
    public const BOSS_CHALLENGE_LOSS_LOOT_SPAN = 3;

    public const GLORY_WIN = 10;

    public const GLORY_LOSS = 2;

    public const RELICS_RITE_WIN = 25;

    public const RELICS_RITE_LOSS = 8;

    /**
     * Catálogo curto de arquétipos do Rito da Temporada.
     *
     * @var array<string, array{
     *     name: string,
     *     icon: string,
     *     tone: string,
     *     blurb: string,
     *     hp: int,
     *     atk: int,
     *     def: int,
     *     spd: int,
     *     heal_chance: float,
     *     phases: array{
     *         julgamento: array{label: string, strike: string, heal_verb: string, atk_mult: float, armor_pierce: float, heal_chance: float},
     *         prova: array{label: string, strike: string, heal_verb: string, atk_mult: float, armor_pierce: float, heal_chance: float, mirror?: bool},
     *         veredito: array{label: string, strike: string, heal_verb: string, atk_mult: float, armor_pierce: float, heal_chance: float}
     *     }
     * }>
     */
    public const ARCHETYPES = [
        'eclipse' => [
            'name' => 'O Eclipse do Arquivo',
            'icon' => '🌑',
            'tone' => '#6d28d9',
            'blurb' => 'Julga quem não abriu o material. Nas sombras, o conhecimento omitido cobra o preço.',
            'hp' => 130,
            'atk' => 22,
            'def' => 14,
            'spd' => 11,
            'heal_chance' => 0.06,
            'phases' => [
                'julgamento' => [
                    'label' => 'Julgamento',
                    'strike' => 'sela as páginas contra',
                    'heal_verb' => 'absorve margens e recupera',
                    'atk_mult' => 1.0,
                    'armor_pierce' => 0.0,
                    'heal_chance' => 0.06,
                ],
                'prova' => [
                    'label' => 'Prova',
                    'strike' => 'rasga o capítulo em',
                    'heal_verb' => 'reescreve o trecho e recupera',
                    'atk_mult' => 1.12,
                    'armor_pierce' => 0.15,
                    'heal_chance' => 0.08,
                ],
                'veredito' => [
                    'label' => 'Veredito',
                    'strike' => 'apaga a referência de',
                    'heal_verb' => 'fecha o tomo e recupera',
                    'atk_mult' => 1.28,
                    'armor_pierce' => 0.25,
                    'heal_chance' => 0.04,
                ],
            ],
        ],
        'forge' => [
            'name' => 'A Forja da Entrega',
            'icon' => '🔥',
            'tone' => '#e85d04',
            'blurb' => 'Esquenta quanto mais o prazo aperta. Ignora parte da defesa quando a prova começa.',
            'hp' => 125,
            'atk' => 24,
            'def' => 12,
            'spd' => 10,
            'heal_chance' => 0.04,
            'phases' => [
                'julgamento' => [
                    'label' => 'Julgamento',
                    'strike' => 'aquece o molde contra',
                    'heal_verb' => 'tempera o aço e recupera',
                    'atk_mult' => 1.0,
                    'armor_pierce' => 0.1,
                    'heal_chance' => 0.04,
                ],
                'prova' => [
                    'label' => 'Prova',
                    'strike' => 'bate o martelo em',
                    'heal_verb' => 'resfria a lâmina e recupera',
                    'atk_mult' => 1.15,
                    'armor_pierce' => 0.35,
                    'heal_chance' => 0.05,
                ],
                'veredito' => [
                    'label' => 'Veredito',
                    'strike' => 'despeja o metal em',
                    'heal_verb' => 'endurece a forja e recupera',
                    'atk_mult' => 1.32,
                    'armor_pierce' => 0.45,
                    'heal_chance' => 0.03,
                ],
            ],
        ],
        'tide' => [
            'name' => 'A Maré da Frequência',
            'icon' => '🌊',
            'tone' => '#0ea5e9',
            'blurb' => 'Cura a si mesma se a turma falta. A presença é a âncora; a ausência vira onda.',
            'hp' => 140,
            'atk' => 20,
            'def' => 15,
            'spd' => 9,
            'heal_chance' => 0.14,
            'phases' => [
                'julgamento' => [
                    'label' => 'Julgamento',
                    'strike' => 'empurra a corrente contra',
                    'heal_verb' => 'enche a maré e recupera',
                    'atk_mult' => 1.0,
                    'armor_pierce' => 0.0,
                    'heal_chance' => 0.16,
                ],
                'prova' => [
                    'label' => 'Prova',
                    'strike' => 'afoga a falta de',
                    'heal_verb' => 'sobe a enchente e recupera',
                    'atk_mult' => 1.08,
                    'armor_pierce' => 0.05,
                    'heal_chance' => 0.28,
                ],
                'veredito' => [
                    'label' => 'Veredito',
                    'strike' => 'rompe o dique sobre',
                    'heal_verb' => 'segura a vazante e recupera',
                    'atk_mult' => 1.22,
                    'armor_pierce' => 0.1,
                    'heal_chance' => 0.18,
                ],
            ],
        ],
        'mirror' => [
            'name' => 'O Espelho da Média',
            'icon' => '🪞',
            'tone' => '#a855f7',
            'blurb' => 'Copia o estilo da classe do desafiante. Na Prova, o golpe vira o seu próprio.',
            'hp' => 120,
            'atk' => 21,
            'def' => 13,
            'spd' => 13,
            'heal_chance' => 0.08,
            'phases' => [
                'julgamento' => [
                    'label' => 'Julgamento',
                    'strike' => 'reflete o golpe em',
                    'heal_verb' => 'polida a face e recupera',
                    'atk_mult' => 1.0,
                    'armor_pierce' => 0.0,
                    'heal_chance' => 0.08,
                ],
                'prova' => [
                    'label' => 'Prova',
                    'strike' => 'imita o estilo de',
                    'heal_verb' => 'espelha a cura e recupera',
                    'atk_mult' => 1.1,
                    'armor_pierce' => 0.1,
                    'heal_chance' => 0.1,
                    'mirror' => true,
                ],
                'veredito' => [
                    'label' => 'Veredito',
                    'strike' => 'quebra o reflexo sobre',
                    'heal_verb' => 'estilhaça e recupera',
                    'atk_mult' => 1.3,
                    'armor_pierce' => 0.2,
                    'heal_chance' => 0.06,
                ],
            ],
        ],
        'vault' => [
            'name' => 'O Cofre do Prazo',
            'icon' => '⏳',
            'tone' => '#ca8a04',
            'blurb' => 'Tranca o tempo. Quem chega atrasado sente o peso da fechadura.',
            'hp' => 135,
            'atk' => 19,
            'def' => 18,
            'spd' => 8,
            'heal_chance' => 0.05,
            'phases' => [
                'julgamento' => [
                    'label' => 'Julgamento',
                    'strike' => 'trava o ponteiro em',
                    'heal_verb' => 'retesa a mola e recupera',
                    'atk_mult' => 1.0,
                    'armor_pierce' => 0.05,
                    'heal_chance' => 0.05,
                ],
                'prova' => [
                    'label' => 'Prova',
                    'strike' => 'cobra a multa de',
                    'heal_verb' => 'lacrar o cofre e recupera',
                    'atk_mult' => 1.14,
                    'armor_pierce' => 0.2,
                    'heal_chance' => 0.07,
                ],
                'veredito' => [
                    'label' => 'Veredito',
                    'strike' => 'estoura o prazo sobre',
                    'heal_verb' => 'selar a câmara e recupera',
                    'atk_mult' => 1.35,
                    'armor_pierce' => 0.3,
                    'heal_chance' => 0.04,
                ],
            ],
        ],
        'ember' => [
            'name' => 'A Brasa da Prova',
            'icon' => '✨',
            'tone' => '#f59e0b',
            'blurb' => 'A chama viva do exame. Rápida, intensa e sem misericórdia no Veredito.',
            'hp' => 115,
            'atk' => 26,
            'def' => 10,
            'spd' => 15,
            'heal_chance' => 0.03,
            'phases' => [
                'julgamento' => [
                    'label' => 'Julgamento',
                    'strike' => 'chispa em',
                    'heal_verb' => 'sopra a brasa e recupera',
                    'atk_mult' => 1.0,
                    'armor_pierce' => 0.05,
                    'heal_chance' => 0.03,
                ],
                'prova' => [
                    'label' => 'Prova',
                    'strike' => 'lança labaredas em',
                    'heal_verb' => 'alimenta o fogo e recupera',
                    'atk_mult' => 1.18,
                    'armor_pierce' => 0.15,
                    'heal_chance' => 0.04,
                ],
                'veredito' => [
                    'label' => 'Veredito',
                    'strike' => 'incendeia',
                    'heal_verb' => 'reacende e recupera',
                    'atk_mult' => 1.4,
                    'armor_pierce' => 0.25,
                    'heal_chance' => 0.02,
                ],
            ],
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::ARCHETYPES;
    }

    public static function keys(): array
    {
        return array_keys(self::ARCHETYPES);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(?string $key): ?array
    {
        if ($key === null || $key === '') {
            return null;
        }

        return self::ARCHETYPES[$key] ?? null;
    }

    public static function label(?string $key): ?string
    {
        return self::get($key)['name'] ?? null;
    }

    public static function difficultyLabel(?string $key): string
    {
        return self::DIFFICULTY_LABELS[$key] ?? self::DIFFICULTY_LABELS[self::DIFFICULTY_NORMAL];
    }

    public static function difficultyPower(?string $key): float
    {
        return self::DIFFICULTY_POWER[$key] ?? self::DIFFICULTY_POWER[self::DIFFICULTY_NORMAL];
    }

    public static function archetypeRule(): In
    {
        return Rule::in(self::keys());
    }

    public static function difficultyRule(): In
    {
        return Rule::in(array_keys(self::DIFFICULTY_POWER));
    }

    /**
     * HP inicial do rito após Marcas (cada marca corta uma fatia, com teto).
     */
    public static function weakenedRaidHp(int $baseHp, int $marks): int
    {
        $weaken = min(self::MARK_WEAKEN_CAP, max(0, $marks) * self::MARK_WEAKEN_PCT);

        return max(1, (int) round($baseHp * (1 - $weaken)));
    }

    public static function raidBaseHp(int $eligibleFighters): int
    {
        $raw = max(1, $eligibleFighters) * self::RAID_HP_PER_FIGHTER;

        return max(self::RAID_HP_FLOOR, $raw);
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     * @return array<int, int>
     */
    public static function bossChallengeWeek(?array $schedule): array
    {
        $week = [];

        foreach (ArenaSchedule::weekdays() as $weekday) {
            $week[$weekday] = self::bossChallengeLimitForWeekday($schedule, $weekday);
        }

        return $week;
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     */
    public static function bossChallengeDailyLimit(?array $schedule, ?CarbonInterface $now = null): int
    {
        return self::bossChallengeLimitForWeekday($schedule, ArenaSchedule::todayWeekday($now));
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     */
    public static function bossChallengeLimitForWeekday(?array $schedule, int $weekday): int
    {
        $default = self::BOSS_CHALLENGE_DAILY_DEFAULT;
        $day = $schedule[$weekday] ?? $schedule[(string) $weekday] ?? null;

        if (is_array($day) && array_key_exists('daily_limit', $day)) {
            return max(0, min(self::BOSS_CHALLENGE_DAILY_MAX, (int) $day['daily_limit']));
        }

        if (is_numeric($day)) {
            return max(0, min(self::BOSS_CHALLENGE_DAILY_MAX, (int) $day));
        }

        return $default;
    }

    /**
     * @param  array<int|string, mixed>  $days
     * @return array<int, array{daily_limit: int}>
     */
    public static function bossChallengeScheduleFromValidated(array $days): array
    {
        $week = [];

        foreach (ArenaSchedule::weekdays() as $weekday) {
            $day = $days[$weekday] ?? $days[(string) $weekday] ?? [];
            $limit = is_array($day)
                ? (int) ($day['daily_limit'] ?? self::BOSS_CHALLENGE_DAILY_DEFAULT)
                : (int) $day;
            $week[$weekday] = [
                'daily_limit' => max(0, min(self::BOSS_CHALLENGE_DAILY_MAX, $limit)),
            ];
        }

        return $week;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function bossChallengeScheduleRules(string $prefix = 'boss_challenge_days'): array
    {
        $rules = [
            $prefix => ['nullable', 'array'],
        ];

        foreach (ArenaSchedule::weekdays() as $weekday) {
            $rules[$prefix.'.'.$weekday] = ['nullable', 'array'];
            $rules[$prefix.'.'.$weekday.'.daily_limit'] = [
                'required_with:'.$prefix,
                'integer',
                'min:0',
                'max:'.self::BOSS_CHALLENGE_DAILY_MAX,
            ];
        }

        return $rules;
    }
}
