<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Models\SchoolClass;
use App\Services\GameEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameEventController extends Controller
{
    public function __construct(private GameEventService $events) {}

    public function index(Request $request): View
    {
        $student = $request->user();
        $class = $this->currentClass($request);

        $classEvents = GameEvent::query()
            ->with('prizeItem')
            ->where('class_id', $class->id)
            ->whereIn('kind', [GameEvent::KIND_ACTIVITY, GameEvent::KIND_CLASS])
            ->whereIn('status', [GameEvent::STATUS_SCHEDULED, GameEvent::STATUS_LIVE, GameEvent::STATUS_CLOSED])
            ->latest()
            ->get();

        $realmEvents = collect();
        if ($class->area_id) {
            $realmEvents = GameEvent::query()
                ->with('prizeItem')
                ->where('area_id', $class->area_id)
                ->where('kind', GameEvent::KIND_REALM)
                ->whereIn('status', [GameEvent::STATUS_SCHEDULED, GameEvent::STATUS_LIVE, GameEvent::STATUS_CLOSED])
                ->latest()
                ->get();
        }

        return view('student.events', [
            'class' => $class,
            'classEvents' => $classEvents,
            'realmEvents' => $realmEvents,
        ]);
    }

    public function show(Request $request, GameEvent $gameEvent): View
    {
        $class = $this->currentClass($request);
        $this->assertAccess($gameEvent, $class);
        $this->authorize('view', $gameEvent);

        $gameEvent->load(['questions', 'prizeItem', 'activity']);
        $state = $this->events->playState($gameEvent, $request->user(), $class);
        $ranking = $gameEvent->isClosed() || ($state['attempt']['finished'] ?? false)
            ? $this->events->ranking($gameEvent)
            : collect();

        return view('student.event-show', [
            'class' => $class,
            'event' => $gameEvent,
            'state' => $state,
            'ranking' => $ranking,
            'pollUrl' => route('student.events.state', $gameEvent),
            'answerUrl' => route('student.events.answer', $gameEvent),
            'joinUrl' => route('student.events.join', $gameEvent),
        ]);
    }

    public function join(Request $request, GameEvent $gameEvent): RedirectResponse|JsonResponse
    {
        $class = $this->currentClass($request);
        $this->assertAccess($gameEvent, $class);
        $this->authorize('play', $gameEvent);

        $this->events->startOrResumeAttempt($gameEvent, $request->user(), $class);

        if ($request->expectsJson()) {
            return response()->json($this->events->playState($gameEvent, $request->user(), $class));
        }

        return redirect()->route('student.events.show', $gameEvent);
    }

    public function state(Request $request, GameEvent $gameEvent): JsonResponse
    {
        $class = $this->currentClass($request);
        $this->assertAccess($gameEvent, $class);
        $this->authorize('play', $gameEvent);

        return response()->json($this->events->playState($gameEvent, $request->user(), $class));
    }

    public function answer(Request $request, GameEvent $gameEvent): JsonResponse
    {
        $class = $this->currentClass($request);
        $this->assertAccess($gameEvent, $class);
        $this->authorize('play', $gameEvent);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_index' => ['required', 'integer', 'min:0', 'max:3'],
        ]);

        $this->events->answer(
            $gameEvent,
            $request->user(),
            $class,
            (int) $data['question_id'],
            (int) $data['selected_index'],
        );

        return response()->json($this->events->playState($gameEvent->fresh(), $request->user(), $class));
    }

    private function currentClass(Request $request): SchoolClass
    {
        $student = $request->user();
        $id = $request->session()->get('current_class_id');
        if ($id) {
            $class = $student->classes()->where('classes.id', $id)->first();
            if ($class) {
                return $class;
            }
        }

        $class = $student->classes()->orderBy('name')->first();
        abort_unless($class, 404);

        return $class;
    }

    private function assertAccess(GameEvent $gameEvent, SchoolClass $class): void
    {
        if ($gameEvent->isRealmEvent()) {
            abort_unless((int) $class->area_id === (int) $gameEvent->area_id, 404);

            return;
        }

        abort_unless((int) $gameEvent->class_id === (int) $class->id, 404);
    }
}
