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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
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

    private function currentClass(Request $request): ?SchoolClass
    {
        $student = $request->user();
        $classId = $request->session()->get('student_class_id');

        if ($classId) {
            $class = $student->enrolledClasses()->where('classes.id', $classId)->first();
            if ($class) {
                return $class;
            }
        }

        return $student->enrolledClasses()->orderBy('name')->first();
    }
}
