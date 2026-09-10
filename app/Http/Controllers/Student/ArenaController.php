<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Duel;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\TeamBattle;
use App\Models\User;
use App\Services\DuelService;
use App\Services\TeamBattleService;
use App\Support\ArenaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ArenaController extends Controller
{
    public function __construct(
        private DuelService $duels,
        private TeamBattleService $teamBattles,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();
        $this->authorize('viewAsStudent', $class);

        $opponents = $class->students()
            ->where('users.id', '!=', $student->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $peer) => $peer->hasApprovedPersona())
            ->values();

        $pendingIncoming = Duel::query()
            ->with(['challenger', 'opponent'])
            ->where('class_id', $class->id)
            ->where('opponent_id', $student->id)
            ->where('status', Duel::STATUS_PENDING)
            ->latest()
            ->get();

        $pendingOutgoing = Duel::query()
            ->with(['challenger', 'opponent'])
            ->where('class_id', $class->id)
            ->where('challenger_id', $student->id)
            ->where('status', Duel::STATUS_PENDING)
            ->latest()
            ->get();

        $history = Duel::query()
            ->with(['challenger', 'opponent', 'winner'])
            ->where('class_id', $class->id)
            ->where(function ($query) use ($student) {
                $query->where('challenger_id', $student->id)
                    ->orWhere('opponent_id', $student->id);
            })
            ->where('status', Duel::STATUS_RESOLVED)
            ->latest('resolved_at')
            ->limit(12)
            ->get();

        $enrollment = $student->enrollmentIn($class);
        $canChallenge = $class->isArenaOpen() && $student->hasApprovedPersona();

        $ownTeam = $student->teamInClass($class);
        $otherGuilds = $class->teams()
            ->with('members')
            ->orderBy('name')
            ->get()
            ->when($ownTeam, fn ($teams) => $teams->where('id', '!=', $ownTeam->id)->values());

        $pendingGuildIncoming = collect();
        $pendingGuildOutgoing = collect();
        $guildHistory = collect();
        $guildResolvedToday = 0;
        $guildNotices = [];
        $canChallengeGuild = $canChallenge && $ownTeam !== null;

        if ($ownTeam) {
            $pendingGuildIncoming = TeamBattle::query()
                ->with(['challengerTeam', 'opponentTeam', 'challenger'])
                ->where('class_id', $class->id)
                ->where('opponent_team_id', $ownTeam->id)
                ->where('status', TeamBattle::STATUS_PENDING)
                ->latest()
                ->get();

            $pendingGuildOutgoing = TeamBattle::query()
                ->with(['challengerTeam', 'opponentTeam', 'challenger'])
                ->where('class_id', $class->id)
                ->where('challenger_team_id', $ownTeam->id)
                ->where('status', TeamBattle::STATUS_PENDING)
                ->latest()
                ->get();

            $guildHistory = TeamBattle::query()
                ->with(['challengerTeam', 'opponentTeam', 'winnerTeam'])
                ->where('class_id', $class->id)
                ->where(function ($query) use ($ownTeam) {
                    $query->where('challenger_team_id', $ownTeam->id)
                        ->orWhere('opponent_team_id', $ownTeam->id);
                })
                ->where('status', TeamBattle::STATUS_RESOLVED)
                ->latest('resolved_at')
                ->limit(12)
                ->get();

            $guildResolvedToday = $this->teamBattles->resolvedTodayForTeam($class, $ownTeam);
            $guildNotices = $canChallengeGuild
                ? $this->teamBattles->challengeNotices($class, $ownTeam, $otherGuilds)
                : [];
        }

        return view('student.arena', [
            'class' => $class,
            'student' => $student,
            'enrollment' => $enrollment,
            'opponents' => $opponents,
            'pendingIncoming' => $pendingIncoming,
            'pendingOutgoing' => $pendingOutgoing,
            'history' => $history,
            'hall' => $this->duels->hall($class, publicOnly: true),
            'resolvedToday' => $this->duels->resolvedTodayCount($class, $student),
            'dailyLimit' => $class->arenaDailyLimit(),
            'cooldownMinutes' => $class->arenaCooldownMinutes(),
            'cooldownLabel' => $class->arenaCooldownLabel(),
            'canChallenge' => $canChallenge,
            'opponentNotices' => $canChallenge
                ? $this->duels->challengeNotices($class, $student, $opponents)
                : [],
            'ownTeam' => $ownTeam,
            'otherGuilds' => $otherGuilds,
            'pendingGuildIncoming' => $pendingGuildIncoming,
            'pendingGuildOutgoing' => $pendingGuildOutgoing,
            'guildHistory' => $guildHistory,
            'guildResolvedToday' => $guildResolvedToday,
            'guildDailyLimit' => TeamBattle::DAILY_RESOLVED_LIMIT,
            'canChallengeGuild' => $canChallengeGuild,
            'guildNotices' => $guildNotices,
            'notifyUrl' => ArenaUrl::route('student.notifications'),
            'markReadUrl' => ArenaUrl::route('student.notifications.read'),
        ]);
    }

    public function challenge(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'opponent_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $opponent = User::query()->findOrFail($data['opponent_id']);

        try {
            $duel = $this->duels->challenge($class, $request->user(), $opponent);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.arena.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.arena.show', $duel)
            ->with('success', 'Desafio enviado! Aguarde o colega aceitar — a batalha abre sozinha.');
    }

    public function show(Request $request, Duel $duel): View|RedirectResponse
    {
        $student = $request->user();
        abort_unless($duel->involves($student), 404);

        $class = $duel->schoolClass;
        $this->authorize('viewAsStudent', $class);

        if ($duel->isPending() && $duel->opponent_id === $student->id) {
            return redirect()->route('student.arena.index');
        }

        if ($duel->status === Duel::STATUS_DECLINED) {
            return redirect()
                ->route('student.arena.index')
                ->with('success', 'Este desafio foi recusado.');
        }

        $duel->load(['challenger', 'opponent', 'winner']);

        $challengerEnrollment = $duel->challenger->enrollmentIn($class);
        $opponentEnrollment = $duel->opponent->enrollmentIn($class);

        return view('student.duel', [
            'class' => $class,
            'student' => $student,
            'duel' => $duel,
            'challengerEnrollment' => $challengerEnrollment,
            'opponentEnrollment' => $opponentEnrollment,
            'statusUrl' => ArenaUrl::route('student.arena.status', $duel),
            'notifyUrl' => ArenaUrl::route('student.notifications'),
            'markReadUrl' => ArenaUrl::route('student.notifications.read'),
        ]);
    }

    public function status(Request $request, Duel $duel): JsonResponse
    {
        $student = $request->user();
        abort_unless($duel->involves($student), 404);
        $this->authorize('viewAsStudent', $duel->schoolClass);

        $duel->refresh();

        return response()->json([
            'status' => $duel->status,
            'redirect' => match ($duel->status) {
                Duel::STATUS_RESOLVED => ArenaUrl::route('student.arena.show', $duel),
                Duel::STATUS_DECLINED, Duel::STATUS_EXPIRED => ArenaUrl::route('student.arena.index'),
                default => null,
            },
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $student = $request->user();

        $duelChallenges = Duel::query()
            ->with('challenger')
            ->where('opponent_id', $student->id)
            ->where('status', Duel::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(function (Duel $duel) {
                $challengerLabel = $duel->challenger->arenaName() ?: $duel->challenger->name;

                return [
                    'id' => 'duel-'.$duel->id,
                    'kind' => 'duel',
                    'duel_id' => $duel->id,
                    'challenger_name' => $duel->challenger->name,
                    'challenger_arena' => $duel->challenger->arenaName(),
                    'message' => "{$challengerLabel} te desafiou para um duelo. Aceita a batalha?",
                    'accept_url' => ArenaUrl::route('student.arena.accept', $duel),
                    'decline_url' => ArenaUrl::route('student.arena.decline', $duel),
                    'show_url' => ArenaUrl::route('student.arena.show', $duel),
                ];
            });

        $guildTeamIds = $student->teams()->pluck('teams.id');

        $guildChallenges = TeamBattle::query()
            ->with(['challengerTeam', 'challenger'])
            ->whereIn('opponent_team_id', $guildTeamIds)
            ->where('status', TeamBattle::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(function (TeamBattle $battle) {
                $teamName = $battle->challengerTeam->name;

                return [
                    'id' => 'guild-'.$battle->id,
                    'kind' => 'guild',
                    'team_battle_id' => $battle->id,
                    'challenger_name' => $battle->challenger->name,
                    'challenger_arena' => $battle->challenger->arenaName(),
                    'message' => "A guilda {$teamName} desafiou a sua. Aceita a batalha de guildas?",
                    'accept_url' => ArenaUrl::route('student.arena.guild.accept', $battle),
                    'decline_url' => ArenaUrl::route('student.arena.guild.decline', $battle),
                    'show_url' => ArenaUrl::route('student.arena.guild.show', $battle),
                ];
            })
            ->values();

        return response()->json([
            'challenges' => $duelChallenges->concat($guildChallenges)->values(),
        ]);
    }

    public function accept(Request $request, Duel $duel): RedirectResponse|JsonResponse
    {
        abort_unless($duel->opponent_id === $request->user()->id, 403);
        $this->authorize('viewAsStudent', $duel->schoolClass);

        try {
            $resolved = $this->duels->accept($duel, $request->user());
        } catch (ValidationException $exception) {
            if ($this->wantsArenaJson($request)) {
                throw $exception;
            }

            return redirect()
                ->route('student.arena.index')
                ->withErrors($exception->errors());
        }

        $battleUrl = ArenaUrl::route('student.arena.show', $resolved);

        if ($this->wantsArenaJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => $battleUrl,
            ]);
        }

        return redirect()
            ->to($battleUrl)
            ->with('success', 'Combate iniciado!');
    }

    public function decline(Request $request, Duel $duel): RedirectResponse|JsonResponse
    {
        abort_unless($duel->opponent_id === $request->user()->id, 403);
        $this->authorize('viewAsStudent', $duel->schoolClass);

        try {
            $this->duels->decline($duel, $request->user());
        } catch (ValidationException $exception) {
            if ($this->wantsArenaJson($request)) {
                throw $exception;
            }

            return redirect()
                ->route('student.arena.index')
                ->withErrors($exception->errors());
        }

        if ($this->wantsArenaJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => null,
                'message' => 'Desafio recusado.',
            ]);
        }

        return redirect()
            ->route('student.arena.index')
            ->with('success', 'Desafio recusado.');
    }

    public function challengeGuild(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'opponent_team_id' => ['required', 'integer', 'exists:teams,id'],
        ]);

        $opponentTeam = Team::query()->findOrFail($data['opponent_team_id']);

        try {
            $battle = $this->teamBattles->challenge($class, $request->user(), $opponentTeam);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.arena.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.arena.guild.show', $battle)
            ->with('success', 'Desafio de guilda enviado! Aguarde o aceite — a batalha abre sozinha.');
    }

    public function showGuild(Request $request, TeamBattle $teamBattle): View|RedirectResponse
    {
        $student = $request->user();
        abort_unless($teamBattle->involvesStudent($student), 404);

        $class = $teamBattle->schoolClass;
        $this->authorize('viewAsStudent', $class);

        $ownTeam = $student->teamInClass($class);

        if ($teamBattle->isPending() && $ownTeam && $teamBattle->opponent_team_id === $ownTeam->id) {
            return redirect()->route('student.arena.index');
        }

        if ($teamBattle->status === TeamBattle::STATUS_DECLINED) {
            return redirect()
                ->route('student.arena.index')
                ->with('success', 'Este desafio de guilda foi recusado.');
        }

        $teamBattle->load(['challengerTeam', 'opponentTeam', 'winnerTeam', 'challenger', 'acceptedBy']);

        return view('student.team-battle', [
            'class' => $class,
            'student' => $student,
            'battle' => $teamBattle,
            'ownTeam' => $ownTeam,
            'statusUrl' => ArenaUrl::route('student.arena.guild.status', $teamBattle),
            'notifyUrl' => ArenaUrl::route('student.notifications'),
            'markReadUrl' => ArenaUrl::route('student.notifications.read'),
        ]);
    }

    public function statusGuild(Request $request, TeamBattle $teamBattle): JsonResponse
    {
        $student = $request->user();
        abort_unless($teamBattle->involvesStudent($student), 404);
        $this->authorize('viewAsStudent', $teamBattle->schoolClass);

        $teamBattle->refresh();

        return response()->json([
            'status' => $teamBattle->status,
            'redirect' => match ($teamBattle->status) {
                TeamBattle::STATUS_RESOLVED => ArenaUrl::route('student.arena.guild.show', $teamBattle),
                TeamBattle::STATUS_DECLINED => ArenaUrl::route('student.arena.index'),
                default => null,
            },
        ]);
    }

    public function acceptGuild(Request $request, TeamBattle $teamBattle): RedirectResponse|JsonResponse
    {
        $student = $request->user();
        $this->authorize('viewAsStudent', $teamBattle->schoolClass);

        $ownTeam = $student->teamInClass($teamBattle->schoolClass);
        abort_unless($ownTeam && $teamBattle->opponent_team_id === $ownTeam->id, 403);

        try {
            $resolved = $this->teamBattles->accept($teamBattle, $student);
        } catch (ValidationException $exception) {
            if ($this->wantsArenaJson($request)) {
                throw $exception;
            }

            return redirect()
                ->route('student.arena.index')
                ->withErrors($exception->errors());
        }

        $battleUrl = ArenaUrl::route('student.arena.guild.show', $resolved);

        if ($this->wantsArenaJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => $battleUrl,
            ]);
        }

        return redirect()
            ->to($battleUrl)
            ->with('success', 'Batalha de guildas iniciada!');
    }

    public function declineGuild(Request $request, TeamBattle $teamBattle): RedirectResponse|JsonResponse
    {
        $student = $request->user();
        $this->authorize('viewAsStudent', $teamBattle->schoolClass);

        $ownTeam = $student->teamInClass($teamBattle->schoolClass);
        abort_unless($ownTeam && $teamBattle->opponent_team_id === $ownTeam->id, 403);

        try {
            $this->teamBattles->decline($teamBattle, $student);
        } catch (ValidationException $exception) {
            if ($this->wantsArenaJson($request)) {
                throw $exception;
            }

            return redirect()
                ->route('student.arena.index')
                ->withErrors($exception->errors());
        }

        if ($this->wantsArenaJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => null,
                'message' => 'Desafio de guilda recusado.',
            ]);
        }

        return redirect()
            ->route('student.arena.index')
            ->with('success', 'Desafio de guilda recusado.');
    }

    private function wantsArenaJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
            || str_contains((string) $request->header('Accept'), 'application/json');
    }

    private function currentClass(Request $request): ?SchoolClass
    {
        $student = $request->user();
        $id = $request->session()->get('current_class_id');
        if ($id) {
            $class = $student->classes()->where('classes.id', $id)->first();
            if ($class) {
                return $class;
            }
        }

        return $student->classes()->orderBy('name')->first();
    }
}
