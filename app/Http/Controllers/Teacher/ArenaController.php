<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\DuelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArenaController extends Controller
{
    public function __construct(private DuelService $duels) {}

    public function open(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $this->duels->toggleArena($schoolClass, true);

        return $this->redirectToArena($schoolClass, 'Arena aberta. Os alunos já podem se desafiar.');
    }

    public function close(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $this->duels->toggleArena($schoolClass, false);

        return $this->redirectToArena($schoolClass, 'Arena fechada.');
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'arena_open' => ['required', 'boolean'],
            'arena_cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'arena_daily_limit' => ['required', 'integer', 'min:1', 'max:50'],
        ], [
            'arena_open.required' => 'Informe se a arena está aberta ou fechada.',
            'arena_open.boolean' => 'O status da arena precisa ser aberto ou fechado.',
            'arena_cooldown_minutes.required' => 'Informe o tempo de espera entre batalhas.',
            'arena_cooldown_minutes.integer' => 'O tempo de espera precisa ser um número inteiro de minutos.',
            'arena_cooldown_minutes.min' => 'O tempo de espera não pode ser negativo.',
            'arena_cooldown_minutes.max' => 'O tempo de espera não pode passar de 7 dias.',
            'arena_daily_limit.required' => 'Informe quantas batalhas são permitidas no dia.',
            'arena_daily_limit.integer' => 'A quantidade de batalhas precisa ser um número inteiro.',
            'arena_daily_limit.min' => 'É preciso permitir pelo menos 1 batalha por dia.',
            'arena_daily_limit.max' => 'O limite diário não pode passar de 50 batalhas.',
        ]);

        $this->duels->updateSettings($schoolClass, [
            'arena_open' => $request->boolean('arena_open'),
            'arena_cooldown_minutes' => (int) $data['arena_cooldown_minutes'],
            'arena_daily_limit' => (int) $data['arena_daily_limit'],
        ]);

        return $this->redirectToArena($schoolClass, 'Configurações da arena salvas.');
    }

    private function redirectToArena(SchoolClass $schoolClass, string $message): RedirectResponse
    {
        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'arena'])
            ->with('success', $message);
    }
}
