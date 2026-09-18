<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\RealmDuel;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\DuelService;
use App\Services\RealmDuelService;
use App\Support\ArenaSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ArenaController extends Controller
{
    public function __construct(
        private DuelService $duels,
        private RealmDuelService $realmDuels,
    ) {}

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

        $data = $request->validate(
            [
                ...ArenaSchedule::rules('days'),
                ...ArenaSchedule::guildRules('guild_days'),
                'arena_weekly_quota' => ['required', 'integer', 'min:0', 'max:21'],
            ],
            [
                ...ArenaSchedule::messages('days'),
                ...ArenaSchedule::guildMessages('guild_days'),
                'arena_weekly_quota.required' => 'Informe a cota semanal de duelos (0 desliga).',
                'arena_weekly_quota.min' => 'A cota semanal não pode ser negativa.',
                'arena_weekly_quota.max' => 'A cota semanal máxima é 21.',
            ],
        );

        $this->duels->updateSettings(
            $schoolClass,
            ArenaSchedule::fromValidated($data['days']),
            ArenaSchedule::guildFromValidated($data['guild_days']),
            (int) $data['arena_weekly_quota'],
        );

        return $this->redirectToArena($schoolClass, 'Configurações da arena salvas.');
    }

    public function arrange(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'challenger_id' => ['required', 'integer', 'exists:users,id'],
            'opponent_id' => ['required', 'integer', 'exists:users,id', 'different:challenger_id'],
        ], [
            'opponent_id.different' => 'Escolha dois alunos diferentes.',
        ]);

        $challenger = User::query()->findOrFail($data['challenger_id']);
        $opponent = User::query()->findOrFail($data['opponent_id']);

        try {
            $duel = $this->duels->staffArrange($schoolClass, $challenger, $opponent, $request->user());
        } catch (ValidationException $exception) {
            return $this->redirectToArena($schoolClass)->withErrors($exception->errors());
        }

        $winnerName = $duel->winner?->arenaName() ?: $duel->winner?->name;

        return $this->redirectToArena(
            $schoolClass,
            "Duelo marcado e resolvido. Vencedor: {$winnerName}.",
        );
    }

    public function cancelRealm(Request $request, SchoolClass $schoolClass, RealmDuel $realmDuel): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        abort_unless(
            (int) $realmDuel->challenger_class_id === (int) $schoolClass->id
            || (int) $realmDuel->opponent_class_id === (int) $schoolClass->id,
            404,
        );

        try {
            $this->realmDuels->staffCancel($realmDuel);
        } catch (ValidationException $exception) {
            return $this->redirectToArena($schoolClass, arenaTab: 'turmas')->withErrors($exception->errors());
        }

        return $this->redirectToArena($schoolClass, 'Desafio entre turmas cancelado.', 'turmas');
    }

    public function updateRealm(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $area = $schoolClass->area;
        abort_unless($area, 404);

        $data = $request->validate(
            ArenaSchedule::rules('realm_days'),
            ArenaSchedule::messages('realm_days', realm: true),
        );

        $this->realmDuels->updateSettings($area, ArenaSchedule::fromValidated($data['realm_days']));

        return $this->redirectToArena($schoolClass, 'Configurações da arena entre turmas salvas.', 'turmas');
    }

    private function redirectToArena(SchoolClass $schoolClass, ?string $message = null, string $arenaTab = 'turma'): RedirectResponse
    {
        $response = redirect()
            ->route('teacher.classes.show', [
                'schoolClass' => $schoolClass,
                'tab' => 'arena',
                'arena_tab' => $arenaTab,
            ]);

        return $message ? $response->with('success', $message) : $response;
    }
}
