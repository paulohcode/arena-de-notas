<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use App\Services\ActivityReminderService;
use App\Services\DuelService;
use App\Services\GradeCalculator;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(
        private RankingService $ranking,
        private GradeCalculator $grades,
        private DuelService $duels,
        private ActivityReminderService $reminders,
    ) {}

    public function home(Request $request): View
    {
        $areas = Area::query()
            ->active()
            ->withCount('classes')
            ->orderBy('name')
            ->get();

        return view('ranking.home', [
            'areas' => $areas,
            'canEditMap' => $request->user()?->isAdmin() ?? false,
        ]);
    }

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $schoolClass->loadMissing('area');
        abort_unless($schoolClass->area?->is_active, 404);

        $user = $request->user();
        $publicOnly = ! $this->canSeeStaffDetails($user, $schoolClass);
        $players = $this->ranking->players($schoolClass, $publicOnly);
        $guilds = $this->ranking->guilds($schoolClass);

        $self = null;
        if ($user?->isStudent()) {
            $self = collect($this->ranking->players($schoolClass))->first(
                fn (array $row) => $row['student']->id === $user->id
            );
        }

        return view('ranking.show', [
            'class' => $schoolClass,
            'players' => $players,
            'guilds' => $guilds,
            'self' => $self,
            'rankingUrl' => route('ranking.live', $schoolClass),
            'canOpenStudentProfile' => $this->canOpenStudentProfile($user, $schoolClass),
            'arenaHall' => $this->duels->hall($schoolClass, $publicOnly),
            'arenaOpen' => $schoolClass->isArenaOpen(),
        ]);
    }

    public function guild(Request $request, SchoolClass $schoolClass, Team $team): View
    {
        abort_unless($team->class_id === $schoolClass->id, 404);
        $schoolClass->loadMissing('area');
        abort_unless($schoolClass->area?->is_active, 404);

        $score = $this->ranking->guildScore($schoolClass, $team);
        $position = $this->ranking->guildPosition($team->id, $schoolClass);
        $user = $request->user();
        $canSeeAll = $this->canSeeStaffDetails($user, $schoolClass);
        $canOpenStudentProfile = $this->canOpenStudentProfile($user, $schoolClass);
        $team->loadMissing('members');
        $viewerIsGuildMember = $user?->isStudent() && $team->members->contains('id', $user->id);

        $members = $team->members->map(function ($student) use ($schoolClass, $canSeeAll, $canOpenStudentProfile) {
            $visible = (bool) $student->enrollmentIn($schoolClass)?->ranking_visible;
            $hidden = ! $visible && ! $canSeeAll;

            return [
                'name' => $hidden ? 'Membro oculto' : $student->name,
                'character_name' => $hidden ? null : $student->arenaName(),
                'hidden' => $hidden,
                'profile_url' => (! $hidden && $canOpenStudentProfile)
                    ? route('teacher.students.show', [$schoolClass, $student])
                    : null,
            ];
        });

        $entries = $team->ledgerEntries()->with('activity')->latest()->limit(12)->get();

        return view('ranking.guild', [
            'class' => $schoolClass,
            'team' => $team,
            'score' => $score,
            'position' => $position,
            'members' => $members,
            'entries' => $entries,
            'guildMissionAlerts' => ($canSeeAll || $viewerIsGuildMember)
                ? $this->reminders->guildPendingMissions($team, $schoolClass)
                : collect(),
        ]);
    }

    public function live(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $schoolClass->loadMissing('area');
        abort_unless($schoolClass->area?->is_active, 404);

        $user = $request->user();
        $publicOnly = ! $this->canSeeStaffDetails($user, $schoolClass);

        $players = collect($this->ranking->players($schoolClass, $publicOnly))->map(fn (array $row) => [
            'id' => $row['student']->id,
            'position' => $row['position'],
            'name' => $row['student']->name,
            'character_name' => $row['student']->arenaName(),
            'average' => $row['average'],
            'xp' => $row['xp'],
            'xp_progress' => $row['xp_progress'],
            'level_name' => $row['level_name'],
            'badge_count' => $row['badge_count'],
            'team' => $row['team'],
        ]);

        $guilds = collect($this->ranking->guilds($schoolClass))->map(fn (array $row) => [
            'id' => $row['team']->id,
            'position' => $row['position'],
            'name' => $row['team']->name,
            'color' => $row['team']->color,
            'emblem' => $row['team']->emblemIcon(),
            'score' => $row['score'],
            'members' => $row['members'],
        ]);

        return response()->json([
            'players' => $players,
            'guilds' => $guilds,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function canSeeStaffDetails(?User $user, SchoolClass $schoolClass): bool
    {
        return (bool) $user?->can('viewStaffDetails', $schoolClass);
    }

    private function canOpenStudentProfile(?User $user, SchoolClass $schoolClass): bool
    {
        return (bool) $user?->can('manage', $schoolClass);
    }
}
