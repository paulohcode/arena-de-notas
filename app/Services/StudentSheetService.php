<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentSheetService
{
    public function __construct(
        private GradeCalculator $grades,
        private RankingService $ranking,
        private ActivityReminderService $reminders,
    ) {}

    /**
     * @return array{
     *     class: SchoolClass,
     *     student: User,
     *     enrollment: ?Enrollment,
     *     team: ?Team,
     *     average: float,
     *     breakdown: list<array{label: string, score: float, weight: int, kind: string, graded: bool}>,
     *     level: array{key: string, name: string, min: int, next: int|null, progress: float},
     *     position: int|null,
     *     guildPosition: int|null,
     *     players: list<array<string, mixed>>,
     *     guilds: list<array<string, mixed>>,
     *     entries: Collection,
     *     badges: Collection,
     *     guildMissionAlerts: Collection
     * }
     */
    public function data(User $student, SchoolClass $class, bool $publicPlayersOnly = false): array
    {
        $enrollment = $student->enrollmentIn($class);
        $team = $student->teamInClass($class);

        $entries = $class->ledgerEntries()
            ->where(function ($query) use ($student, $team) {
                $query->where('student_id', $student->id);
                if ($team) {
                    $query->orWhere('team_id', $team->id);
                }
            })
            ->with('activity')
            ->latest()
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'class' => $class,
            'student' => $student,
            'enrollment' => $enrollment,
            'team' => $team,
            'average' => $this->grades->studentAverage($student, $class),
            'breakdown' => $this->grades->studentBreakdown($student, $class),
            'level' => $this->grades->levelFromXp((int) ($enrollment?->xp ?? 0)),
            'position' => $this->ranking->playerPosition($student, $class),
            'guildPosition' => $team ? $this->ranking->guildPosition($team->id, $class) : null,
            'players' => $this->ranking->players($class, $publicPlayersOnly),
            'guilds' => $this->ranking->guilds($class),
            'entries' => $entries,
            'badges' => $student->badges()->wherePivot('class_id', $class->id)->orderBy('name')->get(),
            'guildMissionAlerts' => $team
                ? $this->reminders->guildPendingMissions($team, $class)
                : collect(),
        ];
    }
}
