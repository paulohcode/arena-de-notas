<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Duel;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\DuelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ArenaController extends Controller
{
    public function __construct(private DuelService $duels) {}

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
            'dailyLimit' => Duel::DAILY_RESOLVED_LIMIT,
            'canChallenge' => $class->isArenaOpen() && $student->hasApprovedPersona(),
            'notifyUrl' => route('student.notifications'),
            'markReadUrl' => route('student.notifications.read'),
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
        $duel = $this->duels->challenge($class, $request->user(), $opponent);

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

        return view('student.duel', [
            'class' => $class,
            'student' => $student,
            'duel' => $duel,
            'statusUrl' => route('student.arena.status', $duel),
            'notifyUrl' => route('student.notifications'),
            'markReadUrl' => route('student.notifications.read'),
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
                Duel::STATUS_RESOLVED => route('student.arena.show', $duel),
                Duel::STATUS_DECLINED, Duel::STATUS_EXPIRED => route('student.arena.index'),
                default => null,
            },
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $student = $request->user();

        $challenges = Duel::query()
            ->with('challenger')
            ->where('opponent_id', $student->id)
            ->where('status', Duel::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(function (Duel $duel) {
                $challengerLabel = $duel->challenger->arenaName() ?: $duel->challenger->name;

                return [
                    'duel_id' => $duel->id,
                    'challenger_name' => $duel->challenger->name,
                    'challenger_arena' => $duel->challenger->arenaName(),
                    'message' => "{$challengerLabel} te desafiou para um duelo. Aceita a batalha?",
                    'accept_url' => route('student.arena.accept', $duel),
                    'decline_url' => route('student.arena.decline', $duel),
                    'show_url' => route('student.arena.show', $duel),
                ];
            })
            ->values();

        return response()->json([
            'challenges' => $challenges,
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

        $battleUrl = route('student.arena.show', $resolved);

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
