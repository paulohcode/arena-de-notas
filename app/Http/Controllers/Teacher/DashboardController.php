<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Duel;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Services\DuelService;
use App\Services\RankingService;
use Illuminate\Http\Request;
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
        $schoolClass->load(['students', 'teams.members', 'activities', 'area', 'teacher']);

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
            'pendingPersonas' => $pendingPersonas,
            'pendingDuels' => $pendingDuels,
            'recentDuels' => $recentDuels,
            'arenaHall' => $this->duels->hall($schoolClass),
        ]);
    }
}
