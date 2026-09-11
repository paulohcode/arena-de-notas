<?php

namespace App\Services;

use App\Models\Duel;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;

class RankingService
{
    public function __construct(private GradeCalculator $grades) {}

    /**
     * @return list<array{
     *     position: int,
     *     student: User,
     *     average: float,
     *     xp: int,
     *     xp_progress: float,
     *     level_name: string,
     *     badge_count: int,
     *     visible: bool,
     *     team: ?string
     * }>
     */
    public function players(SchoolClass $class, bool $publicOnly = false): array
    {
        $rows = [];

        $students = $class->students()
            ->orderBy('name')
            ->withCount([
                'badges as badge_count' => fn ($q) => $q->where('user_badges.class_id', $class->id),
            ])
            ->get();

        foreach ($students as $student) {
            $xp = (int) ($student->pivot?->xp ?? 0);
            $level = $this->grades->levelFromXp($xp);
            $rows[] = [
                'student' => $student,
                'average' => $this->grades->studentAverage($student, $class),
                'xp' => $xp,
                'xp_progress' => $level['progress'],
                'level_name' => $level['name'],
                'badge_count' => (int) ($student->badge_count ?? 0),
                'visible' => (bool) ($student->pivot?->ranking_visible ?? false),
                'team' => $student->teamInClass($class)?->name,
            ];
        }

        $headToHeadWins = $this->headToHeadWinsByPair($class);

        usort($rows, function (array $a, array $b) use ($headToHeadWins) {
            $aId = (int) $a['student']->id;
            $bId = (int) $b['student']->id;
            $aWinsVsB = $headToHeadWins[$aId][$bId] ?? 0;
            $bWinsVsA = $headToHeadWins[$bId][$aId] ?? 0;

            return [$b['average'], $b['xp'], $bWinsVsA, $a['student']->name]
                <=> [$a['average'], $a['xp'], $aWinsVsB, $b['student']->name];
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
     * @return list<array{position: int, team: Team, score: float, members: int}>
     */
    public function guilds(SchoolClass $class): array
    {
        $rows = [];

        foreach ($class->teams()->with(['members'])->withCount('members')->orderBy('name')->get() as $team) {
            $rows[] = [
                'team' => $team,
                'score' => $this->guildScore($class, $team),
                'members' => $team->members_count,
            ];
        }

        usort($rows, function (array $a, array $b) {
            return [$b['score'], $a['team']->name] <=> [$a['score'], $b['team']->name];
        });

        $ranked = [];
        foreach (array_values($rows) as $index => $row) {
            $row['position'] = $index + 1;
            $ranked[] = $row;
        }

        return $ranked;
    }

    /**
     * Score usado no ranking das guildas (Hall das Guildas).
     *
     * Ponderação:
     * - Componente A: nota da guilda (atividades de equipe + ajustes da guilda)
     * - Componente B: média individual dos membros (somente atividades individuais + ajustes, sem "nota equipe")
     * - Score final = média simples dos dois (limitado em 0..100)
     */
    public function guildScore(SchoolClass $class, Team $team): float
    {
        $teamBase = $this->grades->teamScore($team);

        if ($team->members->isEmpty()) {
            return $teamBase;
        }

        $memberAvg = (float) $team->members->avg(
            fn (User $member) => $this->grades->studentAverageIndividual($member, $class)
        );

        return $this->grades->clamp(($teamBase + $memberAvg) / 2);
    }

    public function playerPosition(User $student, SchoolClass $class): ?int
    {
        foreach ($this->players($class) as $row) {
            if ($row['student']->id === $student->id) {
                return $row['position'];
            }
        }

        return null;
    }

    public function guildPosition(int $teamId, SchoolClass $class): ?int
    {
        foreach ($this->guilds($class) as $row) {
            if ($row['team']->id === $teamId) {
                return $row['position'];
            }
        }

        return null;
    }

    /**
     * Vitórias em duelos 1x1 resolvidos da turma: [vencedorId][perdedorId] => quantidade.
     *
     * @return array<int, array<int, int>>
     */
    private function headToHeadWinsByPair(SchoolClass $class): array
    {
        $duels = Duel::query()
            ->where('class_id', $class->id)
            ->where('status', Duel::STATUS_RESOLVED)
            ->whereNotNull('winner_id')
            ->get(['challenger_id', 'opponent_id', 'winner_id']);

        $wins = [];

        foreach ($duels as $duel) {
            $winnerId = (int) $duel->winner_id;
            $loserId = $winnerId === (int) $duel->challenger_id
                ? (int) $duel->opponent_id
                : (int) $duel->challenger_id;

            if ($winnerId === $loserId) {
                continue;
            }

            $wins[$winnerId][$loserId] = ($wins[$winnerId][$loserId] ?? 0) + 1;
        }

        return $wins;
    }
}
