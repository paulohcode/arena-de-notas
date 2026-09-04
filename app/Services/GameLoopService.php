<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Badge;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Support\Facades\DB;

class GameLoopService
{
    public function __construct(
        private GradeCalculator $grades,
        private RankingService $ranking,
    ) {}

    public function recordActivityGrade(SchoolClass $class, Activity $activity, float $score, User $author, ?User $student = null, ?Team $team = null): LedgerEntry
    {
        $score = $this->grades->clamp(min($score, (float) $activity->max_score));
        $delta = $class->isRising()
            ? $score
            : $score - $activity->max_score;

        return $this->commit($class, function () use ($class, $activity, $score, $delta, $author, $student, $team) {
            return $this->upsertActivityEntry($class, $activity, $score, $delta, $author, $student, $team);
        }, $student, $team, [
            'kind' => 'activity',
            'score' => $score,
            'label' => $activity->name,
        ]);
    }

    /**
     * Lança várias notas da mesma atividade de uma vez (alunos ou guildas).
     *
     * @param  array<int|string, mixed>  $scoresByTargetId
     */
    public function recordActivityGradesBatch(SchoolClass $class, Activity $activity, array $scoresByTargetId, User $author): int
    {
        $beforePlayers = $this->snapshotPlayers($class);
        $beforeGuilds = $this->snapshotGuilds($class);
        $isTeam = $activity->isTeam();

        $saved = DB::transaction(function () use ($class, $activity, $scoresByTargetId, $author, $isTeam) {
            $rows = [];

            foreach ($scoresByTargetId as $targetId => $rawScore) {
                if ($rawScore === null || $rawScore === '') {
                    continue;
                }

                $score = $this->grades->clamp(min((float) $rawScore, (float) $activity->max_score));
                $delta = $class->isRising()
                    ? $score
                    : $score - $activity->max_score;

                if ($isTeam) {
                    $team = Team::query()->where('class_id', $class->id)->find((int) $targetId);
                    if (! $team) {
                        continue;
                    }
                    $this->upsertActivityEntry($class, $activity, $score, $delta, $author, team: $team);
                    $rows[] = ['team' => $team, 'student' => null, 'score' => $score];
                } else {
                    $student = User::query()->find((int) $targetId);
                    if (! $student || ! $class->students()->where('users.id', $student->id)->exists()) {
                        continue;
                    }
                    $this->upsertActivityEntry($class, $activity, $score, $delta, $author, student: $student);
                    $rows[] = ['team' => null, 'student' => $student, 'score' => $score];
                }
            }

            return $rows;
        });

        foreach ($saved as $row) {
            $this->notifyGrade($class, $row['student'], $row['team'], [
                'kind' => 'activity',
                'score' => $row['score'],
                'label' => $activity->name,
            ]);
        }

        $this->applyXpAndLevels($class, $beforePlayers);
        $this->notifyRankChanges($class, $beforePlayers, $beforeGuilds);
        $this->awardBadges($class);

        return count($saved);
    }

    private function upsertActivityEntry(
        SchoolClass $class,
        Activity $activity,
        float $score,
        float $delta,
        User $author,
        ?User $student = null,
        ?Team $team = null,
    ): LedgerEntry {
        $query = LedgerEntry::query()
            ->where('activity_id', $activity->id)
            ->where('type', 'activity');

        if ($student) {
            $query->where('student_id', $student->id);
        }
        if ($team) {
            $query->where('team_id', $team->id);
        }

        $entry = $query->first() ?? new LedgerEntry;
        $entry->fill([
            'class_id' => $class->id,
            'activity_id' => $activity->id,
            'student_id' => $student?->id,
            'team_id' => $team?->id,
            'created_by' => $author->id,
            'type' => 'activity',
            'raw_score' => $score,
            'delta' => $delta,
            'reason' => $activity->name,
        ]);
        $entry->save();

        return $entry;
    }

    public function recordAdjustment(SchoolClass $class, float $delta, string $reason, User $author, ?User $student = null, ?Team $team = null): LedgerEntry
    {
        $type = $delta >= 0 ? 'bonus' : 'penalty';

        return $this->commit($class, function () use ($class, $delta, $reason, $author, $student, $team, $type) {
            return LedgerEntry::create([
                'class_id' => $class->id,
                'student_id' => $student?->id,
                'team_id' => $team?->id,
                'created_by' => $author->id,
                'type' => $type,
                'delta' => $delta,
                'reason' => $reason,
            ]);
        }, $student, $team, [
            'kind' => $type,
            'delta' => $delta,
            'label' => $reason,
        ]);
    }

    public function adjustBehavior(SchoolClass $class, User $student, float $delta, User $author): LedgerEntry
    {
        $enrollment = $student->enrollmentIn($class);
        abort_unless($enrollment !== null, 404);

        return $this->commit($class, function () use ($class, $student, $delta, $author, $enrollment) {
            $old = (float) $enrollment->behavior_score;
            $new = $this->grades->clamp($old + $delta);
            $applied = $new - $old;

            $enrollment->behavior_score = $new;
            $enrollment->save();

            return LedgerEntry::create([
                'class_id' => $class->id,
                'student_id' => $student->id,
                'created_by' => $author->id,
                'type' => 'behavior',
                'raw_score' => $new,
                'delta' => $applied,
                'reason' => 'Comportamento',
            ]);
        }, $student, null, [
            'kind' => $delta >= 0 ? 'gain' : 'loss',
            'delta' => $delta,
            'label' => 'Comportamento',
        ]);
    }

    /**
     * @param  callable(): LedgerEntry  $persist
     * @param  array{kind: string, score?: float, delta?: float, label: string}  $event
     */
    private function commit(SchoolClass $class, callable $persist, ?User $student, ?Team $team, array $event): LedgerEntry
    {
        $beforePlayers = $this->snapshotPlayers($class);
        $beforeGuilds = $this->snapshotGuilds($class);

        $entry = DB::transaction(function () use ($persist) {
            return $persist();
        });

        $this->notifyGrade($class, $student, $team, $event);
        $this->applyXpAndLevels($class, $beforePlayers);
        $this->notifyRankChanges($class, $beforePlayers, $beforeGuilds);
        $this->awardBadges($class);

        return $entry;
    }

    /**
     * @return array<int, array{average: float, xp: int, position: int, level: string}>
     */
    private function snapshotPlayers(SchoolClass $class): array
    {
        $snap = [];
        foreach ($this->ranking->players($class) as $row) {
            $level = $this->grades->levelFromXp($row['xp']);
            $snap[$row['student']->id] = [
                'average' => $row['average'],
                'xp' => $row['xp'],
                'position' => $row['position'],
                'level' => $level['name'],
            ];
        }

        return $snap;
    }

    /**
     * @return array<int, int>
     */
    private function snapshotGuilds(SchoolClass $class): array
    {
        $snap = [];
        foreach ($this->ranking->guilds($class) as $row) {
            $snap[$row['team']->id] = $row['position'];
        }

        return $snap;
    }

    /**
     * @param  array{kind: string, score?: float, delta?: float, label: string}  $event
     */
    private function notifyGrade(SchoolClass $class, ?User $student, ?Team $team, array $event): void
    {
        $recipients = collect();
        if ($student) {
            $recipients->push($student);
        }
        if ($team) {
            $recipients = $recipients->merge($team->members);
        }

        foreach ($recipients->unique('id') as $user) {
            if ($event['kind'] === 'activity') {
                $score = $event['score'] ?? 0;
                $title = $team ? 'Nota da guilda' : 'Nota lançada';
                $message = $team
                    ? "A guilda {$team->name} recebeu {$score} em {$event['label']}."
                    : "Você recebeu {$score} em {$event['label']}.";
                $user->notify(new GameAlert('grade', $title, $message, $event));

                continue;
            }

            $delta = $event['delta'] ?? 0;
            $sign = $delta > 0 ? '+'.rtrim(rtrim(number_format($delta, 2, '.', ''), '0'), '.') : rtrim(rtrim(number_format($delta, 2, '.', ''), '0'), '.');
            $title = $delta >= 0 ? 'Pontos recuperados' : 'Pontos perdidos';
            $message = $delta >= 0
                ? "Você recuperou {$sign} ({$event['label']})."
                : "Você perdeu {$sign} ({$event['label']}).";
            $user->notify(new GameAlert($delta >= 0 ? 'gain' : 'loss', $title, $message, $event));
        }
    }

    /**
     * @param  array<int, array{average: float, xp: int, position: int, level: string}>  $before
     */
    private function applyXpAndLevels(SchoolClass $class, array $before): void
    {
        foreach ($this->ranking->players($class) as $row) {
            $student = $row['student'];
            $enrollment = $student->enrollmentIn($class);
            if (! $enrollment) {
                continue;
            }

            $old = $before[$student->id] ?? ['average' => 0, 'xp' => $enrollment->xp, 'level' => 'Iniciante'];
            $gain = max(0, $row['average'] - $old['average']);
            if ($gain > 0) {
                $enrollment->xp = (int) $enrollment->xp + (int) round($gain * 10);
                $enrollment->save();
            }

            $newLevel = $this->grades->levelFromXp((int) $enrollment->xp);
            if ($newLevel['name'] !== $old['level'] && $enrollment->xp > ($old['xp'] ?? 0)) {
                $student->notify(new GameAlert('level', 'Subiu de nível!', "Você agora é {$newLevel['name']}.", [
                    'level' => $newLevel['name'],
                ]));
            }
        }
    }

    /**
     * @param  array<int, array{average: float, xp: int, position: int, level: string}>  $beforePlayers
     * @param  array<int, int>  $beforeGuilds
     */
    private function notifyRankChanges(SchoolClass $class, array $beforePlayers, array $beforeGuilds): void
    {
        foreach ($this->ranking->players($class) as $row) {
            $old = $beforePlayers[$row['student']->id]['position'] ?? null;
            if ($old && $old !== $row['position']) {
                $up = $row['position'] < $old;
                $row['student']->notify(new GameAlert(
                    $up ? 'rank_up' : 'rank_down',
                    $up ? 'Você subiu no ranking!' : 'Você desceu no ranking',
                    $up
                        ? "Foi do {$old}º para o {$row['position']}º lugar."
                        : "Foi do {$old}º para o {$row['position']}º lugar.",
                    ['from' => $old, 'to' => $row['position']],
                ));
            }
        }

        foreach ($this->ranking->guilds($class) as $row) {
            $old = $beforeGuilds[$row['team']->id] ?? null;
            if ($old && $old !== $row['position']) {
                $up = $row['position'] < $old;
                $title = $up ? 'A guilda subiu no Hall!' : 'A guilda caiu no Hall';
                $message = "A guilda {$row['team']->name} foi para o {$row['position']}º lugar.";
                foreach ($row['team']->members as $member) {
                    $member->notify(new GameAlert($up ? 'guild_up' : 'guild_down', $title, $message, [
                        'from' => $old,
                        'to' => $row['position'],
                    ]));
                }
            }
        }
    }

    private function awardBadges(SchoolClass $class): void
    {
        $players = $this->ranking->players($class);
        $guilds = $this->ranking->guilds($class);
        $firstGuild = $guilds[0]['team'] ?? null;

        foreach ($players as $row) {
            $student = $row['student'];
            $gradedCount = LedgerEntry::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->where('type', 'activity')
                ->count();

            $hadPenalty = LedgerEntry::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->where('type', 'penalty')
                ->exists();
            $hadBonusAfter = LedgerEntry::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->where('type', 'bonus')
                ->exists();

            $team = $student->teamInClass($class);
            $teamFirst = $firstGuild && $team && $firstGuild->id === $team->id;

            $this->grant($student, $class, 'primeira-nota', $gradedCount >= 1);
            $this->grant($student, $class, 'media-70', $row['average'] >= 70);
            $this->grant($student, $class, 'media-90', $row['average'] >= 90);
            $this->grant($student, $class, 'top-3', $row['position'] <= 3);
            $this->grant($student, $class, 'guilda-primeiro', $teamFirst);
            $this->grant($student, $class, 'recuperou', $hadPenalty && $hadBonusAfter);
            $this->grant($student, $class, 'cinco-atividades', $gradedCount >= 5);
        }
    }

    private function grant(User $student, SchoolClass $class, string $slug, bool $condition): void
    {
        if (! $condition) {
            return;
        }

        $badge = Badge::query()->where('slug', $slug)->first();
        if (! $badge) {
            return;
        }

        $already = $student->badges()
            ->where('badge_id', $badge->id)
            ->wherePivot('class_id', $class->id)
            ->exists();

        if ($already) {
            return;
        }

        $student->badges()->attach($badge->id, ['class_id' => $class->id]);
        $student->notify(new GameAlert('badge', 'Medalha desbloqueada!', "Você ganhou: {$badge->name}.", [
            'badge' => $badge->name,
            'icon' => $badge->icon,
        ]));
    }
}
