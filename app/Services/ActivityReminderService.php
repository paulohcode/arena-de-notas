<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Support\Collection;

class ActivityReminderService
{
    public function activityHasStarted(Activity $activity): bool
    {
        return $activity->ledgerEntries()
            ->where('type', 'activity')
            ->exists();
    }

    /**
     * @return Collection<int, User>
     */
    public function studentsMissingWork(SchoolClass $class, Activity $activity): Collection
    {
        $students = $class->students()
            ->orderBy('name')
            ->orderBy('users.id')
            ->get();

        if ($activity->isTeam()) {
            $class->load(['teams.members']);

            $gradedTeamIds = LedgerEntry::query()
                ->where('activity_id', $activity->id)
                ->where('type', 'activity')
                ->whereNotNull('team_id')
                ->pluck('team_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $gradedMemberIds = [];
            foreach ($class->teams as $team) {
                if (! in_array((int) $team->id, $gradedTeamIds, true)) {
                    continue;
                }

                foreach ($team->members as $member) {
                    $gradedMemberIds[$member->id] = true;
                }
            }

            return $students
                ->reject(fn (User $student) => isset($gradedMemberIds[$student->id]))
                ->values();
        }

        $gradedStudentIds = LedgerEntry::query()
            ->where('activity_id', $activity->id)
            ->where('type', 'activity')
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $gradedSet = array_flip($gradedStudentIds);

        return $students
            ->reject(fn (User $student) => isset($gradedSet[$student->id]))
            ->values();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function pendingMissions(User $student, SchoolClass $class): Collection
    {
        $activities = $class->activities()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($activities->isEmpty()) {
            return collect();
        }

        $activityIds = $activities->pluck('id');
        $team = $student->teamInClass($class);

        $startedIds = LedgerEntry::query()
            ->where('type', 'activity')
            ->whereIn('activity_id', $activityIds)
            ->distinct()
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $startedSet = array_flip($startedIds);

        $gradedIndividualIds = LedgerEntry::query()
            ->where('type', 'activity')
            ->where('student_id', $student->id)
            ->whereIn('activity_id', $activityIds)
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $gradedIndividualSet = array_flip($gradedIndividualIds);

        $gradedTeamIds = [];
        if ($team) {
            $gradedTeamIds = LedgerEntry::query()
                ->where('type', 'activity')
                ->where('team_id', $team->id)
                ->whereIn('activity_id', $activityIds)
                ->pluck('activity_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $gradedTeamSet = array_flip($gradedTeamIds);

        return $activities
            ->filter(function (Activity $activity) use ($startedSet, $gradedIndividualSet, $gradedTeamSet): bool {
                if (! isset($startedSet[$activity->id])) {
                    return false;
                }

                if ($activity->isTeam()) {
                    return ! isset($gradedTeamSet[$activity->id]);
                }

                return ! isset($gradedIndividualSet[$activity->id]);
            })
            ->values();
    }

    /**
     * @return Collection<int, array{activity: Activity, students: Collection<int, User>, guild_wide: bool}>
     */
    public function guildPendingMissions(Team $team, SchoolClass $class): Collection
    {
        $team->loadMissing('members');
        $members = $team->members
            ->sortBy([
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($members->isEmpty()) {
            return collect();
        }

        $activities = $class->activities()
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        if ($activities->isEmpty()) {
            return collect();
        }

        $activityIds = $activities->pluck('id');
        $memberIds = $members->pluck('id');

        $startedIds = LedgerEntry::query()
            ->where('type', 'activity')
            ->whereIn('activity_id', $activityIds)
            ->distinct()
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $startedSet = array_flip($startedIds);

        $gradedIndividual = LedgerEntry::query()
            ->where('type', 'activity')
            ->whereIn('activity_id', $activityIds)
            ->whereIn('student_id', $memberIds)
            ->get(['activity_id', 'student_id']);

        $gradedIndividualSet = [];
        foreach ($gradedIndividual as $entry) {
            $gradedIndividualSet[(int) $entry->activity_id][(int) $entry->student_id] = true;
        }

        $gradedTeamIds = LedgerEntry::query()
            ->where('type', 'activity')
            ->where('team_id', $team->id)
            ->whereIn('activity_id', $activityIds)
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $gradedTeamSet = array_flip($gradedTeamIds);

        return $activities
            ->map(function (Activity $activity) use ($startedSet, $members, $gradedIndividualSet, $gradedTeamSet): ?array {
                if (! isset($startedSet[$activity->id])) {
                    return null;
                }

                if ($activity->isTeam()) {
                    if (isset($gradedTeamSet[$activity->id])) {
                        return null;
                    }

                    return [
                        'activity' => $activity,
                        'students' => $members,
                        'guild_wide' => true,
                    ];
                }

                $missing = $members
                    ->reject(fn (User $student) => isset($gradedIndividualSet[$activity->id][$student->id]))
                    ->values();

                if ($missing->isEmpty()) {
                    return null;
                }

                return [
                    'activity' => $activity,
                    'students' => $missing,
                    'guild_wide' => false,
                ];
            })
            ->filter()
            ->values();
    }

    public function notifyMissingWork(SchoolClass $class, Activity $activity): int
    {
        $students = $this->studentsMissingWork($class, $activity);
        $title = 'Missão pendente';
        $message = 'Missão '.$activity->name.' falta concluir.';

        foreach ($students as $student) {
            $student->notify(new GameAlert('warn', $title, $message, [
                'kind' => 'missing_work',
                'activity_id' => $activity->id,
            ]));
        }

        return $students->count();
    }

    public function dismissMissingWork(User $student, int $activityId): void
    {
        foreach ($student->unreadNotifications as $notification) {
            $data = $notification->data;
            if (($data['type'] ?? null) !== 'warn') {
                continue;
            }
            if (($data['payload']['kind'] ?? null) !== 'missing_work') {
                continue;
            }
            if ((int) ($data['payload']['activity_id'] ?? 0) !== $activityId) {
                continue;
            }

            $notification->markAsRead();
        }
    }
}
