<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Badge;
use App\Models\SchoolClass;
use App\Models\Season;

class SeasonService
{
    /** Score de níveis na escala de nota 0–100 (Iniciante já começa em 60). */
    private const LEVEL_WEIGHTS = [
        'iniciante' => 60,
        'aprendiz' => 70,
        'pleno' => 80,
        'expert' => 90,
        'mestre' => 100,
    ];

    /** Piso da escala de níveis (turma só de Iniciantes). */
    private const LEVEL_SCORE_FLOOR = 60.0;

    /** Medalhas por aluno para o score de medalhas chegar a 100. */
    private const BADGES_PER_STUDENT_FOR_MAX = 5;

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

        foreach ($season->classes()->with(['students', 'teams.members'])->orderBy('name')->orderBy('id')->get() as $class) {
            $rows[] = $this->scoreClass($class);
        }

        return $this->rankClassRows($rows);
    }

    /**
     * Ranking de todas as turmas de um reino, com o mesmo score das temporadas.
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
    public function areaClassRanking(Area $area): array
    {
        $rows = [];

        foreach ($area->classes()->with(['students', 'teams.members'])->orderBy('name')->orderBy('id')->get() as $class) {
            $rows[] = $this->scoreClass($class);
        }

        return $this->rankClassRows($rows);
    }

    /**
     * Calcula a nota da turma em 0–100: média dos alunos.
     * Níveis e medalhas só desempatam o ranking.
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

        $avgIndividual = $studentCount > 0
            ? (float) collect($players)->avg('average')
            : 0.0;

        $guildCount = count($guilds);
        $avgGuilds = $guildCount > 0
            ? (float) collect($guilds)->avg('score')
            : 0.0;

        $levelDistribution = array_fill_keys(array_keys(self::LEVEL_WEIGHTS), 0);
        foreach ($players as $row) {
            $level = $this->grades->levelFromXp($row['xp']);
            $key = $level['key'];
            if (isset($levelDistribution[$key])) {
                $levelDistribution[$key]++;
            }
        }

        $levelScore = $this->levelScore($levelDistribution, $studentCount);
        $totalBadges = $this->classBadgeCount($class);
        $badgeScore = $this->badgeScore($totalBadges, $studentCount);
        $score = $this->grades->clamp($avgIndividual);

        return [
            'position' => 0,
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

    /**
     * @param  array<string, int>  $levelDistribution
     */
    private function levelScore(array $levelDistribution, int $studentCount): float
    {
        if ($studentCount === 0) {
            return 0.0;
        }

        $weightSum = 0;
        foreach ($levelDistribution as $key => $count) {
            $weightSum += ($count * (self::LEVEL_WEIGHTS[$key] ?? self::LEVEL_SCORE_FLOOR));
        }

        return min(100.0, $weightSum / $studentCount);
    }

    private function classBadgeCount(SchoolClass $class): int
    {
        return (int) Badge::query()
            ->join('user_badges', 'badges.id', '=', 'user_badges.badge_id')
            ->join('enrollments', function ($join) use ($class) {
                $join->on('user_badges.user_id', '=', 'enrollments.student_id')
                    ->where('enrollments.class_id', '=', $class->id);
            })
            ->where('user_badges.class_id', $class->id)
            ->count();
    }

    private function badgeScore(int $totalBadges, int $studentCount): float
    {
        if ($studentCount === 0) {
            return 0.0;
        }

        return min(100.0, ($totalBadges / $studentCount / self::BADGES_PER_STUDENT_FOR_MAX) * 100);
    }

    /**
     * @param  list<array{
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
     * }>  $rows
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
    private function rankClassRows(array $rows): array
    {
        usort($rows, function (array $a, array $b): int {
            return [$b['score'], $b['level_score'], $b['badge_score'], $a['class']->name, $a['class']->id]
                <=> [$a['score'], $a['level_score'], $a['badge_score'], $b['class']->name, $b['class']->id];
        });

        $ranked = [];
        foreach (array_values($rows) as $index => $row) {
            $row['position'] = $index + 1;
            $ranked[] = $row;
        }

        return $ranked;
    }
}
