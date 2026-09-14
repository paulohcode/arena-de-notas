<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\BossVigil;
use App\Models\Duel;
use App\Models\EnrollmentCosmetic;
use App\Models\GameEventAttempt;
use App\Models\LedgerEntry;
use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\SeasonClassRite;
use App\Models\TeamBattle;
use App\Models\User;
use App\Support\CosmeticCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    /**
     * @return array{
     *     day: CarbonInterface,
     *     day_label: string,
     *     area: ?Area,
     *     summary: array{
     *         sessions: int,
     *         present: int,
     *         absent: int,
     *         justified: int,
     *         battles: int,
     *         grades: int,
     *         items: int,
     *         event_attempts: int,
     *         badges: int,
     *         active_students: int
     *     },
     *     attendance: array{
     *         sessions: list<array{class_name: string, area_name: string, present: int, absent: int, justified: int, unmarked: int}>,
     *         absentees: list<array{student_name: string, class_name: string, area_name: string, status: string, status_label: string}>,
     *         classes_without_session: list<array{class_name: string, area_name: string}>
     *     },
     *     battles: array{
     *         duels: list<array{class_name: string, area_name: string, challenger: string, opponent: string, winner: ?string, glory_winner: int, glory_loser: int}>,
     *         team_battles: list<array{class_name: string, area_name: string, challenger_team: string, opponent_team: string, winner_team: ?string, glory_winner: int, glory_loser: int}>,
     *         realm_duels: list<array{area_name: string, challenger_class: string, opponent_class: string, challenger: string, opponent: string, winner: ?string, aura_winner: int, aura_loser: int}>,
     *         vigils: list<array{class_name: string, area_name: string, student_name: string, won: bool, mark_earned: bool, glory: int}>,
     *         rites: list<array{class_name: string, area_name: string, season_name: string, event: string, status: string, outcome: ?string}>
     *     },
     *     grades: list<array{student_name: ?string, team_name: ?string, class_name: string, area_name: string, type: string, type_label: string, value: float, reason: ?string, activity_name: ?string}>,
     *     items: list<array{student_name: string, class_name: string, area_name: string, item_key: string, item_name: string, item_icon: string}>,
     *     event_attempts: list<array{student_name: string, class_name: string, area_name: string, event_title: string, correct_count: int, rewards_granted: bool}>,
     *     badges: list<array{student_name: string, class_name: string, area_name: string, badge_name: string, badge_icon: string}>,
     *     active_students: list<array{student_name: string, last_accessed_at: string, classes: list<string>}>
     * }
     */
    public function forDay(CarbonInterface $day, ?Area $area = null): array
    {
        [$startUtc, $endUtc] = $this->utcWindowForDisplayDay($day);
        $heldOn = $day->copy()->timezone((string) config('app.display_timezone'))->toDateString();

        $attendance = $this->attendanceSection($heldOn, $area);
        $battles = $this->battlesSection($startUtc, $endUtc, $area);
        $grades = $this->gradesSection($startUtc, $endUtc, $area);
        $items = $this->itemsSection($startUtc, $endUtc, $area);
        $eventAttempts = $this->eventAttemptsSection($startUtc, $endUtc, $area);
        $badges = $this->badgesSection($startUtc, $endUtc, $area);
        $activeStudents = $this->activeStudentsSection($startUtc, $endUtc, $area);

        $battleCount = count($battles['duels'])
            + count($battles['team_battles'])
            + count($battles['realm_duels'])
            + count($battles['vigils']);

        return [
            'day' => $day->copy()->timezone((string) config('app.display_timezone'))->startOfDay(),
            'day_label' => $day->copy()->timezone((string) config('app.display_timezone'))->format('d/m/Y'),
            'area' => $area,
            'summary' => [
                'sessions' => count($attendance['sessions']),
                'present' => (int) collect($attendance['sessions'])->sum('present'),
                'absent' => (int) collect($attendance['sessions'])->sum(fn (array $row): int => $row['absent'] + $row['justified']),
                'justified' => (int) collect($attendance['sessions'])->sum('justified'),
                'battles' => $battleCount,
                'grades' => count($grades),
                'items' => count($items),
                'event_attempts' => count($eventAttempts),
                'badges' => count($badges),
                'active_students' => count($activeStudents),
            ],
            'attendance' => $attendance,
            'battles' => $battles,
            'grades' => $grades,
            'items' => $items,
            'event_attempts' => $eventAttempts,
            'badges' => $badges,
            'active_students' => $activeStudents,
        ];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function utcWindowForDisplayDay(CarbonInterface $day): array
    {
        $tz = (string) config('app.display_timezone');
        $local = $day->copy()->timezone($tz)->startOfDay();

        return [
            $local->copy()->utc(),
            $local->copy()->endOfDay()->utc(),
        ];
    }

    /**
     * @return array{
     *     sessions: list<array{class_name: string, area_name: string, present: int, absent: int, justified: int, unmarked: int}>,
     *     absentees: list<array{student_name: string, class_name: string, area_name: string, status: string, status_label: string}>,
     *     classes_without_session: list<array{class_name: string, area_name: string}>
     * }
     */
    private function attendanceSection(string $heldOn, ?Area $area): array
    {
        $sessions = AttendanceSession::query()
            ->with([
                'schoolClass.area',
                'records.student',
            ])
            ->whereDate('held_on', $heldOn)
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderBy('id')
            ->get();

        $sessionRows = [];
        $absentees = [];
        $sessionClassIds = [];

        foreach ($sessions as $session) {
            $class = $session->schoolClass;
            if (! $class) {
                continue;
            }

            $sessionClassIds[] = (int) $class->id;
            $present = 0;
            $absent = 0;
            $justified = 0;
            $unmarked = 0;

            foreach ($session->records as $record) {
                match ($record->status) {
                    AttendanceRecord::STATUS_PRESENT => $present++,
                    AttendanceRecord::STATUS_ABSENT => $absent++,
                    AttendanceRecord::STATUS_JUSTIFIED => $justified++,
                    default => $unmarked++,
                };

                if (in_array($record->status, [
                    AttendanceRecord::STATUS_ABSENT,
                    AttendanceRecord::STATUS_JUSTIFIED,
                ], true)) {
                    $absentees[] = [
                        'student_name' => $record->student?->name ?? '—',
                        'class_name' => $class->name,
                        'area_name' => $class->area?->name ?? '—',
                        'status' => $record->status,
                        'status_label' => $record->statusLabel(),
                    ];
                }
            }

            $sessionRows[] = [
                'class_name' => $class->name,
                'area_name' => $class->area?->name ?? '—',
                'present' => $present,
                'absent' => $absent,
                'justified' => $justified,
                'unmarked' => $unmarked,
            ];
        }

        usort($sessionRows, fn (array $a, array $b): int => $this->alphabeticalKeys($a, $b, ['class_name', 'area_name']));
        usort($absentees, fn (array $a, array $b): int => $this->alphabeticalKeys($a, $b, ['student_name', 'class_name', 'area_name', 'status']));

        $classesWithoutSession = SchoolClass::query()
            ->with('area')
            ->when($area, fn (Builder $query) => $query->where('area_id', $area->id))
            ->when($sessionClassIds !== [], fn (Builder $query) => $query->whereNotIn('id', $sessionClassIds))
            ->orderBy('name')
            ->get()
            ->map(fn (SchoolClass $class): array => [
                'class_name' => $class->name,
                'area_name' => $class->area?->name ?? '—',
            ])
            ->sortBy([
                fn (array $row): string => mb_strtolower($row['class_name']),
                fn (array $row): string => mb_strtolower($row['area_name']),
            ], SORT_NATURAL)
            ->values()
            ->all();

        return [
            'sessions' => $sessionRows,
            'absentees' => $absentees,
            'classes_without_session' => $classesWithoutSession,
        ];
    }

    /**
     * @return array{
     *     duels: list<array{class_name: string, area_name: string, challenger: string, opponent: string, winner: ?string, glory_winner: int, glory_loser: int}>,
     *     team_battles: list<array{class_name: string, area_name: string, challenger_team: string, opponent_team: string, winner_team: ?string, glory_winner: int, glory_loser: int}>,
     *     realm_duels: list<array{area_name: string, challenger_class: string, opponent_class: string, challenger: string, opponent: string, winner: ?string, aura_winner: int, aura_loser: int}>,
     *     vigils: list<array{class_name: string, area_name: string, student_name: string, won: bool, mark_earned: bool, glory: int}>,
     *     rites: list<array{class_name: string, area_name: string, season_name: string, event: string, status: string, outcome: ?string}>
     * }
     */
    private function battlesSection(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area): array
    {
        $duels = Duel::query()
            ->with(['schoolClass.area', 'challenger', 'opponent', 'winner'])
            ->where('status', Duel::STATUS_RESOLVED)
            ->whereBetween('resolved_at', [$startUtc, $endUtc])
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderBy('resolved_at')
            ->get()
            ->map(fn (Duel $duel): array => [
                'class_name' => $duel->schoolClass?->name ?? '—',
                'area_name' => $duel->schoolClass?->area?->name ?? '—',
                'challenger' => $duel->challenger?->name ?? '—',
                'opponent' => $duel->opponent?->name ?? '—',
                'winner' => $duel->winner?->name,
                'glory_winner' => (int) $duel->glory_winner,
                'glory_loser' => (int) $duel->glory_loser,
            ])
            ->all();

        $teamBattles = TeamBattle::query()
            ->with(['schoolClass.area', 'challengerTeam', 'opponentTeam', 'winnerTeam'])
            ->where('status', TeamBattle::STATUS_RESOLVED)
            ->whereBetween('resolved_at', [$startUtc, $endUtc])
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderBy('resolved_at')
            ->get()
            ->map(fn (TeamBattle $battle): array => [
                'class_name' => $battle->schoolClass?->name ?? '—',
                'area_name' => $battle->schoolClass?->area?->name ?? '—',
                'challenger_team' => $battle->challengerTeam?->name ?? '—',
                'opponent_team' => $battle->opponentTeam?->name ?? '—',
                'winner_team' => $battle->winnerTeam?->name,
                'glory_winner' => (int) $battle->glory_winner,
                'glory_loser' => (int) $battle->glory_loser,
            ])
            ->all();

        $realmDuels = RealmDuel::query()
            ->with(['area', 'challengerClass', 'opponentClass', 'challenger', 'opponent', 'winner'])
            ->where('status', RealmDuel::STATUS_RESOLVED)
            ->whereBetween('resolved_at', [$startUtc, $endUtc])
            ->when($area, fn (Builder $query) => $query->where('area_id', $area->id))
            ->orderBy('resolved_at')
            ->get()
            ->map(fn (RealmDuel $duel): array => [
                'area_name' => $duel->area?->name ?? '—',
                'challenger_class' => $duel->challengerClass?->name ?? '—',
                'opponent_class' => $duel->opponentClass?->name ?? '—',
                'challenger' => $duel->challenger?->name ?? '—',
                'opponent' => $duel->opponent?->name ?? '—',
                'winner' => $duel->winner?->name,
                'aura_winner' => (int) $duel->aura_winner,
                'aura_loser' => (int) $duel->aura_loser,
            ])
            ->all();

        $vigils = BossVigil::query()
            ->with(['schoolClass.area', 'student'])
            ->where('status', BossVigil::STATUS_RESOLVED)
            ->whereBetween('resolved_at', [$startUtc, $endUtc])
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderBy('resolved_at')
            ->get()
            ->map(fn (BossVigil $vigil): array => [
                'class_name' => $vigil->schoolClass?->name ?? '—',
                'area_name' => $vigil->schoolClass?->area?->name ?? '—',
                'student_name' => $vigil->student?->name ?? '—',
                'won' => (bool) $vigil->won,
                'mark_earned' => (bool) $vigil->mark_earned,
                'glory' => (int) $vigil->glory,
            ])
            ->all();

        $rites = SeasonClassRite::query()
            ->with(['schoolClass.area', 'season'])
            ->where(function (Builder $query) use ($startUtc, $endUtc): void {
                $query->whereBetween('opened_at', [$startUtc, $endUtc])
                    ->orWhereBetween('resolved_at', [$startUtc, $endUtc]);
            })
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderBy('id')
            ->get()
            ->map(function (SeasonClassRite $rite) use ($startUtc, $endUtc): array {
                $events = [];
                if ($rite->opened_at !== null && $rite->opened_at->betweenIncluded($startUtc, $endUtc)) {
                    $events[] = 'aberto';
                }
                if ($rite->resolved_at !== null && $rite->resolved_at->betweenIncluded($startUtc, $endUtc)) {
                    $events[] = 'resolvido';
                }

                return [
                    'class_name' => $rite->schoolClass?->name ?? '—',
                    'area_name' => $rite->schoolClass?->area?->name ?? '—',
                    'season_name' => $rite->season?->name ?? '—',
                    'event' => implode(' + ', $events) ?: $rite->status,
                    'status' => $rite->status,
                    'outcome' => $rite->outcome,
                ];
            })
            ->all();

        return [
            'duels' => $duels,
            'team_battles' => $teamBattles,
            'realm_duels' => $realmDuels,
            'vigils' => $vigils,
            'rites' => $rites,
        ];
    }

    /**
     * @return list<array{student_name: ?string, team_name: ?string, class_name: string, area_name: string, type: string, type_label: string, value: float, reason: ?string, activity_name: ?string}>
     */
    private function gradesSection(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area): array
    {
        return LedgerEntry::query()
            ->with(['student', 'team', 'schoolClass.area', 'activity'])
            ->where(function (Builder $query) use ($startUtc, $endUtc): void {
                $query->where(function (Builder $activityQuery) use ($startUtc, $endUtc): void {
                    $activityQuery->where('type', 'activity')
                        ->whereBetween('updated_at', [$startUtc, $endUtc]);
                })->orWhere(function (Builder $adjustmentQuery) use ($startUtc, $endUtc): void {
                    $adjustmentQuery->whereIn('type', ['bonus', 'penalty', 'behavior'])
                        ->whereBetween('created_at', [$startUtc, $endUtc]);
                });
            })
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (LedgerEntry $entry): array {
                $value = $entry->type === 'activity'
                    ? (float) $entry->raw_score
                    : (float) $entry->delta;

                return [
                    'student_name' => $entry->student?->name,
                    'team_name' => $entry->team?->name,
                    'class_name' => $entry->schoolClass?->name ?? '—',
                    'area_name' => $entry->schoolClass?->area?->name ?? '—',
                    'type' => $entry->type,
                    'type_label' => $this->gradeTypeLabel($entry->type),
                    'value' => $value,
                    'reason' => $entry->reason,
                    'activity_name' => $entry->activity?->name,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{student_name: string, class_name: string, area_name: string, item_key: string, item_name: string, item_icon: string}>
     */
    private function itemsSection(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area): array
    {
        return EnrollmentCosmetic::query()
            ->with(['enrollment.student', 'enrollment.schoolClass.area'])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('enrollment.schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (EnrollmentCosmetic $cosmetic): array {
                $item = CosmeticCatalog::item($cosmetic->item_key);
                $class = $cosmetic->enrollment?->schoolClass;

                return [
                    'student_name' => $cosmetic->enrollment?->student?->name ?? '—',
                    'class_name' => $class?->name ?? '—',
                    'area_name' => $class?->area?->name ?? '—',
                    'item_key' => $cosmetic->item_key,
                    'item_name' => $item['name'] ?? $cosmetic->item_key,
                    'item_icon' => $item['icon'] ?? '✦',
                ];
            })
            ->all();
    }

    /**
     * @return list<array{student_name: string, class_name: string, area_name: string, event_title: string, correct_count: int, rewards_granted: bool}>
     */
    private function eventAttemptsSection(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area): array
    {
        return GameEventAttempt::query()
            ->with(['student', 'schoolClass.area', 'event.area'])
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$startUtc, $endUtc])
            ->when($area, function (Builder $query) use ($area): void {
                $query->where(function (Builder $scope) use ($area): void {
                    $scope->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id))
                        ->orWhereHas('event', fn (Builder $eventQuery) => $eventQuery->where('area_id', $area->id));
                });
            })
            ->orderBy('finished_at')
            ->get()
            ->map(fn (GameEventAttempt $attempt): array => [
                'student_name' => $attempt->student?->name ?? '—',
                'class_name' => $attempt->schoolClass?->name ?? '—',
                'area_name' => $attempt->schoolClass?->area?->name
                    ?? $attempt->event?->area?->name
                    ?? '—',
                'event_title' => $attempt->event?->title ?? '—',
                'correct_count' => (int) $attempt->correct_count,
                'rewards_granted' => (bool) $attempt->rewards_granted,
            ])
            ->all();
    }

    /**
     * @return list<array{student_name: string, class_name: string, area_name: string, badge_name: string, badge_icon: string}>
     */
    private function badgesSection(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area): array
    {
        $rows = DB::table('user_badges')
            ->join('users', 'users.id', '=', 'user_badges.user_id')
            ->join('badges', 'badges.id', '=', 'user_badges.badge_id')
            ->join('classes', 'classes.id', '=', 'user_badges.class_id')
            ->leftJoin('areas', 'areas.id', '=', 'classes.area_id')
            ->whereBetween('user_badges.created_at', [$startUtc, $endUtc])
            ->when($area, fn ($query) => $query->where('classes.area_id', $area->id))
            ->orderBy('user_badges.created_at')
            ->orderBy('user_badges.id')
            ->get([
                'users.name as student_name',
                'classes.name as class_name',
                'areas.name as area_name',
                'badges.name as badge_name',
                'badges.icon as badge_icon',
            ]);

        return $rows->map(fn ($row): array => [
            'student_name' => (string) $row->student_name,
            'class_name' => (string) $row->class_name,
            'area_name' => (string) ($row->area_name ?? '—'),
            'badge_name' => (string) $row->badge_name,
            'badge_icon' => (string) ($row->badge_icon ?: '⭐'),
        ])->all();
    }

    /**
     * @return list<array{student_name: string, last_accessed_at: string, classes: list<string>}>
     */
    private function activeStudentsSection(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area): array
    {
        $students = User::query()
            ->where('role', 'student')
            ->whereBetween('last_accessed_at', [$startUtc, $endUtc])
            ->when($area, function (Builder $query) use ($area): void {
                $query->whereHas('enrollments', function (Builder $enrollmentQuery) use ($area): void {
                    $enrollmentQuery->whereHas('schoolClass', fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
                });
            })
            ->with(['enrollments.schoolClass' => function ($query) use ($area): void {
                $query->when($area, fn (Builder $classQuery) => $classQuery->where('area_id', $area->id));
            }])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $students->map(function (User $student) use ($area): array {
            $classes = $student->enrollments
                ->map(fn ($enrollment) => $enrollment->schoolClass)
                ->filter()
                ->when($area, fn (Collection $classes) => $classes->where('area_id', $area->id))
                ->sortBy('name')
                ->pluck('name')
                ->values()
                ->all();

            return [
                'student_name' => $student->name,
                'last_accessed_at' => $student->last_accessed_at
                    ?->timezone((string) config('app.display_timezone'))
                    ->format('H:i') ?? '—',
                'classes' => $classes,
            ];
        })->all();
    }

    private function gradeTypeLabel(string $type): string
    {
        return match ($type) {
            'activity' => 'Atividade',
            'bonus' => 'Bônus',
            'penalty' => 'Penalidade',
            'behavior' => 'Comportamento',
            default => $type,
        };
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @param  list<string>  $keys
     */
    private function alphabeticalKeys(array $left, array $right, array $keys): int
    {
        $normalize = fn (mixed $value): string => mb_strtolower((string) $value);

        return array_map(fn (string $key): string => $normalize($left[$key] ?? ''), $keys)
            <=> array_map(fn (string $key): string => $normalize($right[$key] ?? ''), $keys);
    }
}
