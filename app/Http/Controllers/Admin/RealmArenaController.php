<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\RealmDuel;
use App\Services\RealmDuelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function open(Area $area): RedirectResponse
    {
        $this->realmDuels->toggleRealmArena($area, true);

        return redirect()
            ->route('admin.areas.arena', $area)
            ->with('success', 'Arena entre turmas aberta.');
    }

    public function close(Area $area): RedirectResponse
    {
        $this->realmDuels->toggleRealmArena($area, false);

        return redirect()
            ->route('admin.areas.arena', $area)
            ->with('success', 'Arena entre turmas fechada.');
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $data = $this->validatedSettings($request);

        $this->realmDuels->updateSettings($area, [
            'realm_arena_open' => $request->boolean('realm_arena_open'),
            'realm_arena_cooldown_minutes' => (int) $data['realm_arena_cooldown_minutes'],
            'realm_arena_daily_limit' => (int) $data['realm_arena_daily_limit'],
        ]);

        return redirect()
            ->route('admin.areas.arena', $area)
            ->with('success', 'Configurações da arena entre turmas salvas.');
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

    /**
     * @return array{realm_arena_open: mixed, realm_arena_cooldown_minutes: mixed, realm_arena_daily_limit: mixed}
     */
    private function validatedSettings(Request $request): array
    {
        return $request->validate([
            'realm_arena_open' => ['required', 'boolean'],
            'realm_arena_cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'realm_arena_daily_limit' => ['required', 'integer', 'min:1', 'max:50'],
        ], [
            'realm_arena_open.required' => 'Informe se a arena entre turmas está aberta ou fechada.',
            'realm_arena_open.boolean' => 'O status da arena entre turmas precisa ser aberto ou fechado.',
            'realm_arena_cooldown_minutes.required' => 'Informe o tempo de espera entre desafios do reino.',
            'realm_arena_cooldown_minutes.integer' => 'O tempo de espera precisa ser um número inteiro de minutos.',
            'realm_arena_cooldown_minutes.min' => 'O tempo de espera não pode ser negativo.',
            'realm_arena_cooldown_minutes.max' => 'O tempo de espera não pode passar de 7 dias.',
            'realm_arena_daily_limit.required' => 'Informe quantos duelos do reino são permitidos no dia.',
            'realm_arena_daily_limit.integer' => 'A quantidade de duelos precisa ser um número inteiro.',
            'realm_arena_daily_limit.min' => 'É preciso permitir pelo menos 1 duelo do reino por dia.',
            'realm_arena_daily_limit.max' => 'O limite diário não pode passar de 50 duelos.',
        ]);
    }
}
