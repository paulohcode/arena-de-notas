<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\RealmDuel;
use App\Services\RealmDuelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RealmArenaController extends Controller
{
    public function __construct(private RealmDuelService $realmDuels) {}

    public function show(Area $area): View
    {
        $area->load(['classes' => fn ($query) => $query->orderBy('name')->with('teacher')]);

        $pendingDuels = RealmDuel::query()
            ->with(['challenger', 'opponent', 'challengerClass', 'opponentClass'])
            ->where('area_id', $area->id)
            ->where('status', RealmDuel::STATUS_PENDING)
            ->latest()
            ->get();

        $recentDuels = RealmDuel::query()
            ->with(['challenger', 'opponent', 'winner', 'challengerClass', 'opponentClass'])
            ->where('area_id', $area->id)
            ->whereIn('status', [RealmDuel::STATUS_RESOLVED, RealmDuel::STATUS_DECLINED])
            ->latest('resolved_at')
            ->limit(40)
            ->get();

        $auraLeaders = AreaBalance::query()
            ->with('student')
            ->where('area_id', $area->id)
            ->where('auras', '>', 0)
            ->orderByDesc('auras')
            ->limit(20)
            ->get();

        return view('admin.areas.arena', [
            'area' => $area,
            'pendingDuels' => $pendingDuels,
            'recentDuels' => $recentDuels,
            'auraLeaders' => $auraLeaders,
        ]);
    }

    public function cancel(Area $area, RealmDuel $realmDuel): RedirectResponse
    {
        abort_unless((int) $realmDuel->area_id === (int) $area->id, 404);

        try {
            $this->realmDuels->staffCancel($realmDuel);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.areas.arena', $area)
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('admin.areas.arena', $area)
            ->with('success', 'Desafio entre turmas cancelado.');
    }
}
