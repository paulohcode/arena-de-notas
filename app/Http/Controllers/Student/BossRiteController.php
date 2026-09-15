<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\BossVigil;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\SeasonClassRite;
use App\Services\ArenaCombatService;
use App\Services\BossRiteService;
use App\Support\ArenaUrl;
use App\Support\BossArchetypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BossRiteController extends Controller
{
    public function __construct(private BossRiteService $rites) {}

    public function challengeShadow(Request $request, Season $season): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $vigil = $this->rites->challengeShadow($season, $class, $request->user());

        return redirect()
            ->route('student.arena.vigil.show', $vigil)
            ->with('success', $vigil->won
                ? 'Vitória! Você ganhou uma Marca do Rito.'
                : 'A Sombra prevaleceu. Tente de novo amanhã.');
    }

    public function showVigil(Request $request, BossVigil $vigil): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);
        abort_unless($vigil->class_id === $class->id, 404);
        abort_unless($vigil->student_id === $request->user()->id, 403);

        $vigil->load(['season', 'student']);

        return view('student.boss-vigil', [
            'class' => $class,
            'student' => $request->user(),
            'vigil' => $vigil,
            'season' => $vigil->season,
            'statusUrl' => $vigil->isPending()
                ? ArenaUrl::route('student.arena.vigil.status', $vigil)
                : null,
        ]);
    }

    public function statusVigil(Request $request, BossVigil $vigil): JsonResponse
    {
        abort_unless($vigil->student_id === $request->user()->id, 403);
        $this->authorize('viewAsStudent', $vigil->schoolClass);

        return response()->json([
            'status' => $vigil->status,
            'redirect' => match ($vigil->status) {
                BossVigil::STATUS_RESOLVED => ArenaUrl::route('student.arena.vigil.show', $vigil).'?replay=1',
                BossVigil::STATUS_DECLINED, BossVigil::STATUS_EXPIRED => ArenaUrl::route('student.arena.index'),
                default => null,
            },
        ]);
    }

    public function acceptVigil(Request $request, BossVigil $vigil): RedirectResponse|JsonResponse
    {
        abort_unless($vigil->student_id === $request->user()->id, 403);
        $this->authorize('viewAsStudent', $vigil->schoolClass);

        try {
            $resolved = $this->rites->acceptStaffChallenge($vigil, $request->user());
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }

        $url = route('student.arena.vigil.show', $resolved).'?replay=1';

        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => ArenaUrl::route('student.arena.vigil.show', $resolved).'?replay=1',
            ]);
        }

        return redirect($url)->with('success', $resolved->won
            ? 'Você venceu o chefão!'
            : 'O chefão prevaleceu. Sem punição de nota.');
    }

    public function declineVigil(Request $request, BossVigil $vigil): RedirectResponse|JsonResponse
    {
        abort_unless($vigil->student_id === $request->user()->id, 403);
        $this->authorize('viewAsStudent', $vigil->schoolClass);

        try {
            $this->rites->declineStaffChallenge($vigil, $request->user());
        } catch (ValidationException $exception) {
            if ($this->wantsJson($request)) {
                throw $exception;
            }

            return back()->withErrors($exception->errors());
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => ArenaUrl::route('student.arena.index'),
            ]);
        }

        return redirect()
            ->route('student.arena.index')
            ->with('success', 'Desafio do chefão recusado.');
    }

    public function showRite(Request $request, SeasonClassRite $rite): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);
        abort_unless($rite->class_id === $class->id, 404);

        $rite->load(['season', 'schoolClass']);

        return view('student.boss-rite', [
            'class' => $class,
            'student' => $request->user(),
            'rite' => $rite,
            'season' => $rite->season,
            'rewardLabel' => GameCurrency::label('relics'),
            'relicsWin' => BossArchetypeCatalog::RELICS_RITE_WIN,
            'relicsLoss' => BossArchetypeCatalog::RELICS_RITE_LOSS,
            'bossFighterId' => ArenaCombatService::BOSS_FIGHTER_ID,
        ]);
    }

    private function wantsJson(Request $request): bool
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
