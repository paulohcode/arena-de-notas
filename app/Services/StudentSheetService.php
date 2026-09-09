<?php

namespace App\Services;

use App\Models\AttendanceRecord;
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
     *     breakdown: list<array{label: string, score: float, weight: int, kind: string, graded: bool, contribution: float}>,
     *     averageBreakdown: array{
     *         lines: list<array{label: string, score: float, weight: int, kind: string, graded: bool, contribution: float}>,
     *         weight_sum: int,
     *         weighted_sum: float,
     *         weighted_average: float,
     *         adjustments: float,
     *         average: float
     *     },
     *     level: array{key: string, name: string, min: int, next: int|null, progress: float},
     *     position: int|null,
     *     guildPosition: int|null,
     *     players: list<array<string, mixed>>,
     *     guilds: list<array<string, mixed>>,
     *     entries: Collection,
     *     badges: Collection,
     *     guildMissionAlerts: Collection,
     *     attendanceRecords: Collection<int, AttendanceRecord>
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

        $averageBreakdown = $this->grades->studentAverageBreakdown($student, $class);

        return [
            'class' => $class,
            'student' => $student,
            'enrollment' => $enrollment,
            'team' => $team,
            'average' => $averageBreakdown['average'],
            'breakdown' => $averageBreakdown['lines'],
            'averageBreakdown' => $averageBreakdown,
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
            'attendanceRecords' => $this->attendanceRecords($student, $class),
        ];
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    private function attendanceRecords(User $student, SchoolClass $class): Collection
    {
        return AttendanceRecord::query()
            ->select('attendance_records.*')
            ->whereBelongsTo($student, 'student')
            ->whereNotNull('attendance_records.status')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.class_id', $class->id)
            ->with('session')
            ->orderByDesc('attendance_sessions.held_on')
            ->orderByDesc('attendance_records.id')
            ->get();
    }
}
