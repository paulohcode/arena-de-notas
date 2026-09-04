<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\SchoolClass;
use App\Models\Season;

class SeasonService
{
    /** Peso de cada nível na pontuação de níveis da turma. */
    private const LEVEL_WEIGHTS = [
        'iniciante' => 1,
        'aprendiz' => 2,
        'pleno' => 3,
        'expert' => 4,
        'mestre' => 5,
    ];

    /** Nível máximo de peso (Mestre = 5) usado para normalizar em 0–100. */
    private const MAX_LEVEL_WEIGHT = 5;

    public function __construct(
        private GradeCalculator $grades,
        private RankingService $ranking,
    ) {}

    /**
     * Retorna o ranking de turmas de uma temporada, ordenado por score decrescente.
     *
     * @return list<array{
     *     position: int,
     *     class: SchoolClass,
     *     score: float,
     *     avg_individual: float,
     *     avg_guilds: float,
     *     level_score: float,
     *     badge_score: float,
     *     level_distribution: array<string, int>,
     *     total_badges: int,
     *     student_count: int,
     * }>
     */
    public function classRanking(Season $season): array
    {
        $rows = [];

        foreach ($season->classes()->with(['students', 'teams.members'])->get() as $class) {
            $rows[] = $this->scoreClass($class);
        }

        usort($rows, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $ranked = [];
        foreach (array_values($rows) as $index => $row) {
            $row['position'] = $index + 1;
            $ranked[] = $row;
        }

        return $ranked;
    }

    /**
     * Calcula o score de uma única turma.
     *
     * @return array{
     *     position: int,
     *     class: SchoolClass,
     *     score: float,
     *     avg_individual: float,
     *     avg_guilds: float,
     *     level_score: float,
     *     badge_score: float,
     *     level_distribution: array<string, int>,
     *     total_badges: int,
     *     student_count: int,
     * }
     */
    public function scoreClass(SchoolClass $class): array
    {
        $players = $this->ranking->players($class);
        $guilds = $this->ranking->guilds($class);
        $studentCount = count($players);

        // Componente 1: média individual dos alunos (0–100)
        $avgIndividual = $studentCount > 0
            ? (float) collect($players)->avg('average')
            : 0.0;

        // Componente 2: média das guildas (0–100)
        $guildCount = count($guilds);
        $avgGuilds = $guildCount > 0
            ? (float) collect($guilds)->avg('score')
            : 0.0;

        // Componente 3: score de níveis ponderado (0–100)
        $levelDistribution = array_fill_keys(array_keys(self::LEVEL_WEIGHTS), 0);
        foreach ($players as $row) {
            $xp = $row['xp'];
            $level = $this->grades->levelFromXp($xp);
            $key = $level['key'];
            if (isset($levelDistribution[$key])) {
                $levelDistribution[$key]++;
            }
        }

        $levelWeightSum = 0;
        foreach ($levelDistribution as $key => $count) {
            $levelWeightSum += ($count * (self::LEVEL_WEIGHTS[$key] ?? 1));
        }

        $maxPossibleLevelWeight = $studentCount * self::MAX_LEVEL_WEIGHT;
        $levelScore = $maxPossibleLevelWeight > 0
            ? min(100.0, ($levelWeightSum / $maxPossibleLevelWeight) * 100)
            : 0.0;

        // Componente 4: score de medalhas (total de medalhas / alunos * 10, max 100)
        $totalBadges = Badge::query()
            ->join('user_badges', 'badges.id', '=', 'user_badges.badge_id')
            ->join('enrollments', function ($join) use ($class) {
                $join->on('user_badges.user_id', '=', 'enrollments.student_id')
                    ->where('enrollments.class_id', '=', $class->id);
            })
            ->where('user_badges.class_id', $class->id)
            ->count();

        $badgeScore = $studentCount > 0
            ? min(100.0, ($totalBadges / $studentCount) * 10)
            : 0.0;

        // Score final: média aritmética dos 4 componentes
        $score = round(($avgIndividual + $avgGuilds + $levelScore + $badgeScore) / 4, 2);

        return [
            'position' => 0, // preenchido no classRanking()
            'class' => $class,
            'score' => $score,
            'avg_individual' => round($avgIndividual, 2),
            'avg_guilds' => round($avgGuilds, 2),
            'level_score' => round($levelScore, 2),
            'badge_score' => round($badgeScore, 2),
            'level_distribution' => $levelDistribution,
            'total_badges' => $totalBadges,
            'student_count' => $studentCount,
        ];
    }
}
