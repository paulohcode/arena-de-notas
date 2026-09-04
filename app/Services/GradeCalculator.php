<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;

class GradeCalculator
{
    private const GUILD_MEMBER_WEIGHT = 0.5;

    public function clamp(float $value): float
    {
        return max(0, min(100, round($value, 2)));
    }

    public function defaultScore(SchoolClass $class): float
    {
        return $class->defaultScore();
    }

    public function teamScore(Team $team): float
    {
        $class = $team->schoolClass;
        $activities = $class->activities()->where('type', 'team')->get();

        $sum = 0.0;
        $weightSum = 0.0;

        foreach ($activities as $activity) {
            $entry = $this->activityEntry($activity, team: $team);
            $score = $entry?->raw_score ?? $this->defaultScore($class);
            $sum += $score * $activity->weight;
            $weightSum += $activity->weight;
        }

        $average = $weightSum > 0 ? $sum / $weightSum : $this->defaultScore($class);
        $adjustments = $this->adjustmentsSum($class, team: $team);

        return $this->clamp($average + $adjustments);
    }

    public function teamMemberAverage(Team $team): float
    {
        $class = $team->schoolClass;
        $members = $team->members;

        if ($members->isEmpty()) {
            return $this->defaultScore($class);
        }

        $average = $members
            ->map(fn (User $student) => $this->studentAverageWithoutTeam($student, $class))
            ->avg();

        return $this->clamp((float) $average);
    }

    public function teamFinalScore(Team $team): float
    {
        $baseScore = $this->teamScore($team);
        $memberAverage = $this->teamMemberAverage($team);

        return $this->clamp(($baseScore + $memberAverage) / 2);
    }

    public function studentAverage(User $student, SchoolClass $class, bool $includeTeamScore = true): float
    {
        $activities = $class->activities()->where('type', 'individual')->get();

        $sum = 0.0;
        $weightSum = 0.0;

        foreach ($activities as $activity) {
            $entry = $this->activityEntry($activity, student: $student);
            $score = $entry?->raw_score ?? $this->defaultScore($class);
            $sum += $score * $activity->weight;
            $weightSum += $activity->weight;
        }

        $behaviorWeight = max(1, (int) ($class->behavior_grade_weight ?? 1));
        $sum += $this->behaviorScore($student, $class) * $behaviorWeight;
        $weightSum += $behaviorWeight;

        $team = $student->teamInClass($class);
        if ($includeTeamScore && $team && $class->activities()->where('type', 'team')->exists()) {
            $weight = max(1, (int) $class->team_grade_weight);
            $sum += $this->teamScore($team) * $weight;
            $weightSum += $weight;
        }

        $average = $weightSum > 0 ? $sum / $weightSum : $this->defaultScore($class);
        $adjustments = $this->adjustmentsSum($class, student: $student);

        return $this->clamp($average + $adjustments);
    }

    public function studentAverageWithoutTeam(User $student, SchoolClass $class): float
    {
        return $this->studentAverage($student, $class, includeTeamScore: false);
    }

    /**
     * Média do aluno considerando apenas atividades individuais + comportamento + ajustes.
     * Usado para a ponderação do ranking de guildas (evita loop com a "nota equipe").
     */
    public function studentAverageIndividual(User $student, SchoolClass $class): float
    {
        $activities = $class->activities()->where('type', 'individual')->get();

        $sum = 0.0;
        $weightSum = 0.0;

        foreach ($activities as $activity) {
            $entry = $this->activityEntry($activity, student: $student);
            $score = $entry?->raw_score ?? $this->defaultScore($class);
            $sum += $score * $activity->weight;
            $weightSum += $activity->weight;
        }

        $behaviorWeight = max(1, (int) ($class->behavior_grade_weight ?? 1));
        $sum += $this->behaviorScore($student, $class) * $behaviorWeight;
        $weightSum += $behaviorWeight;

        $average = $weightSum > 0 ? $sum / $weightSum : $this->defaultScore($class);
        $adjustments = $this->adjustmentsSum($class, student: $student);

        return $this->clamp($average + $adjustments);
    }

    public function behaviorScore(User $student, SchoolClass $class): float
    {
        $enrollment = $student->enrollmentIn($class);

        return $this->clamp((float) ($enrollment?->behavior_score ?? 100));
    }

    /**
     * @return list<array{label: string, score: float, weight: int, kind: string, graded: bool}>
     */
    public function studentBreakdown(User $student, SchoolClass $class): array
    {
        $lines = [];

        foreach ($class->activities()->where('type', 'individual')->orderBy('name')->get() as $activity) {
            $entry = $this->activityEntry($activity, student: $student);
            $lines[] = [
                'label' => $activity->name,
                'score' => $entry?->raw_score ?? $this->defaultScore($class),
                'weight' => (int) $activity->weight,
                'kind' => 'individual',
                'graded' => $entry !== null,
            ];
        }

        $lines[] = [
            'label' => 'Comportamento',
            'score' => $this->behaviorScore($student, $class),
            'weight' => max(1, (int) ($class->behavior_grade_weight ?? 1)),
            'kind' => 'behavior',
            'graded' => true,
        ];

        $team = $student->teamInClass($class);
        if ($team && $class->activities()->where('type', 'team')->exists()) {
            $lines[] = [
                'label' => 'Nota equipe ('.$team->name.')',
                'score' => $this->teamScore($team),
                'weight' => max(1, (int) $class->team_grade_weight),
                'kind' => 'team',
                'graded' => true,
            ];
        }

        $adjustments = $this->adjustmentsSum($class, student: $student);
        if ($adjustments != 0.0) {
            $lines[] = [
                'label' => 'Ajustes (conduta / extra)',
                'score' => $adjustments,
                'weight' => 0,
                'kind' => 'adjust',
                'graded' => true,
            ];
        }

        return $lines;
    }

    public function activityEntry(Activity $activity, ?User $student = null, ?Team $team = null): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->where('activity_id', $activity->id)
            ->where('type', 'activity')
            ->when($student, fn ($q) => $q->where('student_id', $student->id))
            ->when($team, fn ($q) => $q->where('team_id', $team->id))
            ->latest('id')
            ->first();
    }

    public function adjustmentsSum(SchoolClass $class, ?User $student = null, ?Team $team = null): float
    {
        return (float) LedgerEntry::query()
            ->where('class_id', $class->id)
            ->whereNull('activity_id')
            ->whereIn('type', ['bonus', 'penalty', 'adjust'])
            ->when($student, fn ($q) => $q->where('student_id', $student->id))
            ->when($team, fn ($q) => $q->where('team_id', $team->id))
            ->sum('delta');
    }

    /**
     * @return array{key: string, name: string, min: int, next: int|null, progress: float}
     */
    public function levelFromXp(int $xp): array
    {
        $levels = [
            ['key' => 'iniciante', 'name' => 'Iniciante', 'min' => 0],
            ['key' => 'aprendiz', 'name' => 'Aprendiz', 'min' => 100],
            ['key' => 'pleno', 'name' => 'Pleno', 'min' => 250],
            ['key' => 'expert', 'name' => 'Expert', 'min' => 500],
            ['key' => 'mestre', 'name' => 'Mestre', 'min' => 1000],
        ];

        $current = $levels[0];
        $nextMin = $levels[1]['min'];

        foreach ($levels as $index => $level) {
            if ($xp >= $level['min']) {
                $current = $level;
                $nextMin = $levels[$index + 1]['min'] ?? null;
            }
        }

        if ($nextMin === null) {
            $progress = 100.0;
        } else {
            $span = $nextMin - $current['min'];
            $progress = $span > 0 ? (($xp - $current['min']) / $span) * 100 : 100;
        }

        return [
            'key' => $current['key'],
            'name' => $current['name'],
            'min' => $current['min'],
            'next' => $nextMin,
            'progress' => round(min(100, max(0, $progress)), 1),
        ];
    }
}
