<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\GameEvent;
use App\Services\GameEventService;
use App\Support\CosmeticCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameEventController extends Controller
{
    public function __construct(private GameEventService $events) {}

    public function index(Area $area): View
    {
        $events = GameEvent::query()
            ->with(['prizeItem', 'creator'])
            ->where('area_id', $area->id)
            ->where('kind', GameEvent::KIND_REALM)
            ->latest()
            ->get();

        return view('admin.areas.events', [
            'area' => $area,
            'events' => $events,
            'slots' => CosmeticCatalog::SLOTS,
            'rarities' => CosmeticCatalog::RARITIES,
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function store(Request $request, Area $area): RedirectResponse
    {
        $data = $this->validated($request);

        $this->events->create($request->user(), [
            ...$data,
            'kind' => GameEvent::KIND_REALM,
            'publish' => true,
            'prize' => [
                'name' => (string) $request->input('prize_name'),
                'slot' => (string) $request->input('prize_slot'),
                'price' => 0,
                'currency' => CosmeticCatalog::CURRENCY_RELICS,
                'rarity' => (string) $request->input('prize_rarity'),
                'icon' => (string) $request->input('prize_icon'),
                'css' => $request->input('prize_css'),
                'label' => $request->input('prize_label'),
                'combat_bonus_percent' => $request->input('prize_combat_bonus_percent'),
            ],
        ], area: $area);

        return redirect()
            ->route('admin.areas.events.index', $area)
            ->with('success', 'Evento do reino criado.');
    }

    public function show(Area $area, GameEvent $gameEvent): View
    {
        abort_unless(
            (int) $gameEvent->area_id === (int) $area->id && $gameEvent->isRealmEvent(),
            404,
        );

        $gameEvent->load(['questions', 'prizeItem']);

        return view('admin.areas.event-show', [
            'area' => $area,
            'event' => $gameEvent,
            'ranking' => $this->events->ranking($gameEvent),
        ]);
    }

    public function start(Area $area, GameEvent $gameEvent): RedirectResponse
    {
        abort_unless(
            (int) $gameEvent->area_id === (int) $area->id && $gameEvent->isRealmEvent(),
            404,
        );

        $this->events->startLive($gameEvent);

        return redirect()
            ->route('admin.areas.events.show', [$area, $gameEvent])
            ->with('success', 'Evento iniciado ao vivo.');
    }

    public function close(Area $area, GameEvent $gameEvent): RedirectResponse
    {
        abort_unless(
            (int) $gameEvent->area_id === (int) $area->id && $gameEvent->isRealmEvent(),
            404,
        );

        $this->events->close($gameEvent);

        return redirect()
            ->route('admin.areas.events.show', [$area, $gameEvent])
            ->with('success', 'Evento encerrado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
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
            'prize_name' => ['required', 'string', 'max:60'],
            'prize_slot' => ['required', Rule::in(array_keys(CosmeticCatalog::SLOTS))],
            'prize_icon' => ['required', 'string', 'max:32'],
            'prize_rarity' => ['required', Rule::in(array_keys(CosmeticCatalog::RARITIES))],
            'prize_css' => ['nullable', 'string', 'max:30'],
            'prize_label' => ['nullable', 'string', 'max:60'],
            'prize_combat_bonus_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ]);

        $data['questions'] = $this->events->normalizeQuestions($data['questions']);

        return $data;
    }
}
