<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Duel;
use App\Models\GameEvent;
use App\Models\LedgerEntry;
use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use App\Services\DuelService;
use App\Services\RankingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private RankingService $ranking,
        private DuelService $duels,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $classes = ($user->isAdmin()
            ? SchoolClass::query()
            : $user->taughtClasses()
        )
            ->with(['area', 'teacher'])
            ->withCount(['students', 'teams', 'activities'])
            ->latest()
            ->get();

        return view('teacher.dashboard', [
            'classes' => $classes,
            'viewerIsAdmin' => $user->isAdmin(),
        ]);
    }

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $this->authorize('manage', $schoolClass);
        $schoolClass->load([
            'students' => fn ($query) => $query->orderBy('name')->orderBy('users.id'),
            'teams' => fn ($query) => $query->orderBy('name'),
            'teams.members',
            'activities' => fn ($query) => $query->with('gameEvent'),
            'gameEvents' => fn ($query) => $query->with(['prizeItem', 'questions'])
                ->whereIn('kind', [GameEvent::KIND_CLASS, GameEvent::KIND_ACTIVITY])
                ->latest(),
            'area',
            'teacher',
        ]);

        $players = $this->ranking->players($schoolClass);
        $guilds = $this->ranking->guilds($schoolClass);
        $average = count($players) ? round(collect($players)->avg('average'), 1) : 0;

        $scoreThreshold = 50;

        $lowScorePlayers = array_values(
            array_filter($players, fn (array $row) => $row['average'] < $scoreThreshold)
        );

        $gradedStudentIds = LedgerEntry::query()
            ->where('class_id', $schoolClass->id)
            ->where('type', 'activity')
            ->whereNotNull('student_id')
            ->distinct('student_id')
            ->pluck('student_id')
            ->all();

        $ungradedStudents = $schoolClass->students
            ->filter(fn ($s) => ! in_array($s->id, $gradedStudentIds))
            ->values();

        $activityEntries = LedgerEntry::query()
            ->where('class_id', $schoolClass->id)
            ->where('type', 'activity')
            ->whereNotNull('activity_id')
            ->get();

        $studentScoresByActivity = [];
        $teamScoresByActivity = [];
        foreach ($activityEntries as $entry) {
            if ($entry->student_id) {
                $studentScoresByActivity[$entry->activity_id][$entry->student_id] = $entry->raw_score;
            }
            if ($entry->team_id) {
                $teamScoresByActivity[$entry->activity_id][$entry->team_id] = $entry->raw_score;
            }
        }

        $transferClasses = SchoolClass::query()
            ->with('area')
            ->where('id', '!=', $schoolClass->id)
            ->when(
                ! $request->user()->isAdmin(),
                fn ($query) => $query->where('teacher_id', $request->user()->id)
            )
            ->orderBy('name')
            ->get();

        $guildMemberships = [];
        foreach ($schoolClass->teams as $team) {
            foreach ($team->members as $member) {
                $guildMemberships[$member->id] = $team->name;
            }
        }

        $rosterGroups = $this->rosterGroups($schoolClass->students, $schoolClass->teams, $guildMemberships);

        $pendingPersonas = $schoolClass->students
            ->filter(fn ($student) => $student->isPersonaPending())
            ->sortBy('name')
            ->values();

        $pendingDuels = Duel::query()
            ->with(['challenger', 'opponent'])
            ->where('class_id', $schoolClass->id)
            ->where('status', Duel::STATUS_PENDING)
            ->latest()
            ->get();

        $recentDuels = Duel::query()
            ->with(['challenger', 'opponent', 'winner'])
            ->where('class_id', $schoolClass->id)
            ->where('status', Duel::STATUS_RESOLVED)
            ->latest('resolved_at')
            ->limit(20)
            ->get();

        $pendingRealmDuels = collect();
        $recentRealmDuels = collect();

        if ($schoolClass->area_id) {
            $pendingRealmDuels = RealmDuel::query()
                ->with(['challenger', 'opponent', 'challengerClass', 'opponentClass'])
                ->where('area_id', $schoolClass->area_id)
                ->where('status', RealmDuel::STATUS_PENDING)
                ->where(function ($query) use ($schoolClass) {
                    $query->where('challenger_class_id', $schoolClass->id)
                        ->orWhere('opponent_class_id', $schoolClass->id);
                })
                ->latest()
                ->get();

            $recentRealmDuels = RealmDuel::query()
                ->with(['challenger', 'opponent', 'winner', 'challengerClass', 'opponentClass'])
                ->where('area_id', $schoolClass->area_id)
                ->whereIn('status', [RealmDuel::STATUS_RESOLVED, RealmDuel::STATUS_DECLINED])
                ->where(function ($query) use ($schoolClass) {
                    $query->where('challenger_class_id', $schoolClass->id)
                        ->orWhere('opponent_class_id', $schoolClass->id);
                })
                ->latest('resolved_at')
                ->limit(20)
                ->get();
        }

        $attendanceSessions = AttendanceSession::query()
            ->with(['records'])
            ->where('class_id', $schoolClass->id)
            ->orderByDesc('held_on')
            ->orderByDesc('id')
            ->get();

        $activeAttendanceSession = null;
        $requestedSessionId = (int) $request->query('session', 0);
        if ($requestedSessionId > 0) {
            $activeAttendanceSession = $attendanceSessions->firstWhere('id', $requestedSessionId);
        }
        if (! $activeAttendanceSession && $attendanceSessions->isNotEmpty() && $request->query('tab') === 'chamada') {
            $activeAttendanceSession = $attendanceSessions->first();
        }

        if ($activeAttendanceSession) {
            $activeAttendanceSession->load(['records.student']);
        }

        return view('teacher.class-show', [
            'class' => $schoolClass,
            'players' => $players,
            'guilds' => $guilds,
            'average' => $average,
            'lowScorePlayers' => $lowScorePlayers,
            'ungradedStudents' => $ungradedStudents,
            'scoreThreshold' => $scoreThreshold,
            'studentScoresByActivity' => $studentScoresByActivity,
            'teamScoresByActivity' => $teamScoresByActivity,
            'transferClasses' => $transferClasses,
            'guildMemberships' => $guildMemberships,
            'rosterGroups' => $rosterGroups,
            'pendingPersonas' => $pendingPersonas,
            'pendingDuels' => $pendingDuels,
            'recentDuels' => $recentDuels,
            'pendingRealmDuels' => $pendingRealmDuels,
            'recentRealmDuels' => $recentRealmDuels,
            'arenaHall' => $this->duels->hall($schoolClass),
            'attendanceSessions' => $attendanceSessions,
            'activeAttendanceSession' => $activeAttendanceSession,
        ]);
    }

    /**
     * @param  Collection<int, User>  $students
     * @param  Collection<int, Team>  $teams
     * @param  array<int, string>  $guildMemberships
     * @return Collection<int, array{key: string, title: string, emblem: string|null, students: Collection<int, User>}>
     */
    private function rosterGroups(Collection $students, Collection $teams, array $guildMemberships): Collection
    {
        $studentsByName = $students->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $groups = $teams
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function (Team $team) use ($studentsByName): array {
                $memberIds = $team->members->pluck('id');

                return [
                    'key' => 'team-'.$team->id,
                    'title' => $team->name,
                    'emblem' => $team->emblemIcon(),
                    'students' => $studentsByName
                        ->filter(fn (User $student) => $memberIds->contains($student->id))
                        ->values(),
                ];
            })
            ->filter(fn (array $group) => $group['students']->isNotEmpty())
            ->values();

        $unguilded = $studentsByName
            ->reject(fn (User $student) => isset($guildMemberships[$student->id]))
            ->values();

        if ($unguilded->isNotEmpty()) {
            $groups->push([
                'key' => 'unguilded',
                'title' => 'Sem guilda',
                'emblem' => null,
                'students' => $unguilded,
            ]);
        }

        return $groups;
    }
}
