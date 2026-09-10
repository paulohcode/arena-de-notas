<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use App\Models\SchoolClass;
use App\Services\GameEventService;
use App\Support\CosmeticCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameEventController extends Controller
{
    public function __construct(private GameEventService $events) {}

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $this->validated($request, includePrize: true);

        $event = $this->events->create($request->user(), [
            ...$data,
            'kind' => GameEvent::KIND_CLASS,
            'publish' => true,
            'prize' => $this->prizePayload($request),
        ], $schoolClass);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'eventos', 'gameEvent' => $event->id])
            ->with('success', 'Evento da turma criado.');
    }

    public function storeActivity(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $this->validated($request, includePrize: false, activity: true);

        $event = $this->events->create($request->user(), [
            ...$data,
            'kind' => GameEvent::KIND_ACTIVITY,
            'publish' => true,
        ], $schoolClass);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'atividades'])
            ->with('success', 'Atividade-evento criada.');
    }

    public function storeRealm(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($schoolClass->area_id, 404);

        $data = $this->validated($request, includePrize: true);

        $this->events->create($request->user(), [
            ...$data,
            'kind' => GameEvent::KIND_REALM,
            'publish' => true,
            'prize' => $this->prizePayload($request),
        ], area: $schoolClass->area);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'eventos'])
            ->with('success', 'Evento do reino criado.');
    }

    public function start(SchoolClass $schoolClass, GameEvent $gameEvent): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $this->assertEventBelongs($schoolClass, $gameEvent);

        $this->events->startLive($gameEvent);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'eventos', 'gameEvent' => $gameEvent->id])
            ->with('success', 'Evento iniciado ao vivo.');
    }

    public function close(SchoolClass $schoolClass, GameEvent $gameEvent): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $this->assertEventBelongs($schoolClass, $gameEvent);

        $this->events->close($gameEvent);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'eventos', 'gameEvent' => $gameEvent->id])
            ->with('success', 'Evento encerrado.');
    }

    public function show(SchoolClass $schoolClass, GameEvent $gameEvent): View
    {
        $this->authorize('manage', $schoolClass);
        $this->assertEventBelongs($schoolClass, $gameEvent);

        $gameEvent->load(['questions', 'prizeItem', 'activity']);

        return view('teacher.game-event-show', [
            'class' => $schoolClass,
            'event' => $gameEvent,
            'ranking' => $this->events->ranking($gameEvent),
        ]);
    }

    private function assertEventBelongs(SchoolClass $schoolClass, GameEvent $gameEvent): void
    {
        if ($gameEvent->isRealmEvent()) {
            abort_unless((int) $gameEvent->area_id === (int) $schoolClass->area_id, 404);

            return;
        }

        abort_unless((int) $gameEvent->class_id === (int) $schoolClass->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $includePrize, bool $activity = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:120'],
            'mode' => ['required', Rule::in([GameEvent::MODE_LIVE, GameEvent::MODE_WINDOW])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'question_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.prompt' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', Rule::in(['multiple_choice', 'true_false'])],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.options.*' => ['nullable', 'string', 'max:200'],
            'questions.*.correct_index' => ['required', 'integer', 'min:0', 'max:3'],
        ];

        if ($activity) {
            $rules['weight'] = ['required', 'integer', 'min:1', 'max:10'];
            $rules['max_score'] = ['nullable', 'integer', 'min:1', 'max:100'];
            $rules['relics_per_correct'] = ['nullable', 'integer', 'min:0', 'max:100'];
            $rules['seals_per_correct'] = ['nullable', 'integer', 'min:0', 'max:100'];
            $rules['auras_per_correct'] = ['nullable', 'integer', 'min:0', 'max:100'];
        }

        if ($includePrize) {
            $rules = array_merge($rules, [
                'prize_name' => ['required', 'string', 'max:60'],
                'prize_slot' => ['required', Rule::in(array_keys(CosmeticCatalog::SLOTS))],
                'prize_icon' => ['required', 'string', 'max:32'],
                'prize_rarity' => ['required', Rule::in(array_keys(CosmeticCatalog::RARITIES))],
                'prize_css' => ['nullable', 'string', 'max:30'],
                'prize_label' => ['nullable', 'string', 'max:60'],
                'prize_combat_bonus_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            ]);
        }

        $data = $request->validate($rules);
        $data['questions'] = $this->events->normalizeQuestions($data['questions']);
        $data['title'] = $activity ? ($data['title'] ?? $request->input('name', $data['title'])) : $data['title'];

        return $data;
    }

    /**
     * @return array{name: string, slot: string, price: int, currency: string, rarity: string, icon: string, css: ?string, label: ?string, combat_bonus_percent: mixed}
     */
    private function prizePayload(Request $request): array
    {
        return [
            'name' => (string) $request->input('prize_name'),
            'slot' => (string) $request->input('prize_slot'),
            'price' => 0,
            'currency' => CosmeticCatalog::CURRENCY_RELICS,
            'rarity' => (string) $request->input('prize_rarity'),
            'icon' => (string) $request->input('prize_icon'),
            'css' => $request->input('prize_css'),
            'label' => $request->input('prize_label'),
            'combat_bonus_percent' => $request->input('prize_combat_bonus_percent'),
        ];
    }
}
