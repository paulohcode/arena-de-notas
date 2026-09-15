<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\BossVigil;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\SeasonClassRite;
use App\Models\User;
use App\Services\ArenaCombatService;
use App\Services\BossRiteService;
use App\Support\BossArchetypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeasonController extends Controller
{
    public function __construct(private BossRiteService $rites) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $seasons = Season::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('created_by', $user->id))
            ->with(['area', 'classes', 'classRites'])
            ->withCount('classes')
            ->latest()
            ->get();

        $markCounts = [];
        foreach ($seasons as $season) {
            foreach ($season->classes as $class) {
                $markCounts[$season->id][$class->id] = $this->rites->markCount($season, $class);
            }
        }

        return view('teacher.seasons.index', compact('seasons', 'markCounts'));
    }

    public function create(Request $request): View
    {
        return view('teacher.seasons.form', $this->formData($request, new Season, []));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $season = Season::create([
            'area_id' => $data['area_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'boss_archetype' => $data['boss_archetype'] ?? null,
            'boss_difficulty' => $data['boss_difficulty'] ?? BossArchetypeCatalog::DIFFICULTY_NORMAL,
            'vigil_open' => false,
            'created_by' => $request->user()->id,
        ]);

        $this->syncClasses($season, $request->user(), $data['class_ids'] ?? []);

        return redirect()->route('teacher.seasons.index')
            ->with('success', "Temporada \"{$season->name}\" criada.");
    }

    public function edit(Request $request, Season $season): View
    {
        $this->authorizeSeason($request, $season);

        return view('teacher.seasons.form', $this->formData(
            $request,
            $season,
            $season->classes()->pluck('classes.id')->all()
        ));
    }

    public function update(Request $request, Season $season): RedirectResponse
    {
        $this->authorizeSeason($request, $season);

        $data = $this->validated($request);

        $season->update([
            'area_id' => $data['area_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'boss_archetype' => $data['boss_archetype'] ?? null,
            'boss_difficulty' => $data['boss_difficulty'] ?? BossArchetypeCatalog::DIFFICULTY_NORMAL,
        ]);

        $this->syncClasses($season, $request->user(), $data['class_ids'] ?? []);

        return redirect()->route('teacher.seasons.index')
            ->with('success', "Temporada \"{$season->name}\" atualizada.");
    }

    public function destroy(Request $request, Season $season): RedirectResponse
    {
        $this->authorizeSeason($request, $season);

        $season->delete();

        return redirect()->route('teacher.seasons.index')
            ->with('success', 'Temporada excluída.');
    }

    public function openVigil(Request $request, Season $season): RedirectResponse
    {
        $this->authorizeSeason($request, $season);
        $this->rites->setVigilOpen($season, true);

        return back()->with('success', 'Vigília aberta: alunos podem enfrentar a Sombra.');
    }

    public function closeVigil(Request $request, Season $season): RedirectResponse
    {
        $this->authorizeSeason($request, $season);
        $this->rites->setVigilOpen($season, false);

        return back()->with('success', 'Vigília fechada.');
    }

    public function openRite(Request $request, Season $season, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeSeason($request, $season);
        $this->authorizeClass($request, $schoolClass);
        $this->rites->openRite($season, $schoolClass);

        return back()->with('success', "Rito aberto para {$schoolClass->name}.");
    }

    public function resolveRite(Request $request, Season $season, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorizeSeason($request, $season);
        $this->authorizeClass($request, $schoolClass);
        $rite = $this->rites->resolveRite($season, $schoolClass);

        $message = $rite->wasBroken()
            ? "Rito quebrado em {$schoolClass->name}!"
            : "O Rito resistiu em {$schoolClass->name}.";

        return back()->with('success', $message);
    }

    public function bossDesk(Request $request, Season $season): View
    {
        $this->authorizeSeason($request, $season);
        abort_unless($season->hasBoss(), 404);

        $season->load(['area', 'classes']);
        $roster = $this->rites->bossDeskRoster($season);
        $recentChallenges = BossVigil::query()
            ->with(['student', 'schoolClass'])
            ->where('season_id', $season->id)
            ->where('source', BossVigil::SOURCE_STAFF)
            ->whereIn('status', [
                BossVigil::STATUS_PENDING,
                BossVigil::STATUS_RESOLVED,
                BossVigil::STATUS_DECLINED,
            ])
            ->latest()
            ->limit(12)
            ->get();

        return view('teacher.seasons.boss-desk', [
            'season' => $season,
            'roster' => $roster,
            'recentChallenges' => $recentChallenges,
            'boss' => $season->bossMeta(),
        ]);
    }

    public function challengeAsBoss(Request $request, Season $season): RedirectResponse
    {
        $this->authorizeSeason($request, $season);
        abort_unless($season->hasBoss(), 404);

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $class = SchoolClass::query()->findOrFail($data['class_id']);
        $this->authorizeClass($request, $class);
        $student = User::query()->findOrFail($data['student_id']);

        $vigil = $this->rites->staffChallenge($season, $class, $student, $request->user());

        return redirect()
            ->route('teacher.seasons.vigil.show', [$season, $vigil])
            ->with('success', 'Desafio enviado. Aguardando o aluno aceitar.');
    }

    public function showVigil(Request $request, Season $season, BossVigil $vigil): View
    {
        $this->authorizeSeason($request, $season);
        abort_unless($vigil->season_id === $season->id, 404);

        $vigil->load(['season', 'student', 'schoolClass']);

        return view('student.boss-vigil', [
            'class' => $vigil->schoolClass,
            'student' => $vigil->student,
            'vigil' => $vigil,
            'season' => $season,
            'viewerId' => ArenaCombatService::BOSS_FIGHTER_ID,
            'backUrl' => route('teacher.seasons.boss', $season),
            'backLabel' => 'Voltar à mesa do chefão',
            'staffView' => true,
            'statusUrl' => $vigil->isPending()
                ? route('teacher.seasons.vigil.status', [$season, $vigil])
                : null,
        ]);
    }

    public function statusVigil(Request $request, Season $season, BossVigil $vigil): JsonResponse
    {
        $this->authorizeSeason($request, $season);
        abort_unless($vigil->season_id === $season->id, 404);

        return response()->json([
            'status' => $vigil->status,
            'redirect' => match ($vigil->status) {
                BossVigil::STATUS_RESOLVED => route('teacher.seasons.vigil.show', [$season, $vigil]).'?replay=1',
                BossVigil::STATUS_DECLINED, BossVigil::STATUS_EXPIRED => route('teacher.seasons.boss', $season),
                default => null,
            },
        ]);
    }

    public function showRite(Request $request, Season $season, SeasonClassRite $rite): View
    {
        $this->authorizeSeason($request, $season);
        abort_unless($rite->season_id === $season->id, 404);

        $rite->load(['season', 'schoolClass']);

        return view('student.boss-rite', [
            'class' => $rite->schoolClass,
            'student' => $request->user(),
            'rite' => $rite,
            'season' => $season,
            'rewardLabel' => GameCurrency::label('relics'),
            'relicsWin' => BossArchetypeCatalog::RELICS_RITE_WIN,
            'relicsLoss' => BossArchetypeCatalog::RELICS_RITE_LOSS,
            'bossFighterId' => ArenaCombatService::BOSS_FIGHTER_ID,
            'backUrl' => route('teacher.seasons.boss', $season),
            'backLabel' => 'Voltar à mesa do chefão',
            'staffView' => true,
        ]);
    }

    /**
     * @param  list<int>  $selectedClassIds
     * @return array{season: Season, areas: Collection, classes: Collection, selectedClassIds: list<int>, archetypes: array<string, array<string, mixed>>, difficulties: array<string, string>}
     */
    private function formData(Request $request, Season $season, array $selectedClassIds): array
    {
        $user = $request->user();

        $areas = $user->isAdmin()
            ? Area::query()->orderBy('name')->get()
            : $user->areas()->orderBy('name')->get();

        $classes = ($user->isAdmin()
            ? SchoolClass::query()
            : $user->taughtClasses()
        )->with('area')->orderBy('name')->get();

        $archetypes = BossArchetypeCatalog::all();
        $difficulties = BossArchetypeCatalog::DIFFICULTY_LABELS;

        return compact('season', 'areas', 'classes', 'selectedClassIds', 'archetypes', 'difficulties');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $user = $request->user();
        $allowedAreaIds = $user->isAdmin()
            ? Area::query()->pluck('id')->all()
            : $user->areas()->pluck('areas.id')->all();

        return $request->validate([
            'area_id' => ['required', 'integer', Rule::in($allowedAreaIds)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'boss_archetype' => ['nullable', 'string', BossArchetypeCatalog::archetypeRule()],
            'boss_difficulty' => ['nullable', 'string', BossArchetypeCatalog::difficultyRule()],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', 'exists:classes,id'],
        ]);
    }

    /**
     * @param  array<int>  $classIds
     */
    private function syncClasses(Season $season, User $user, array $classIds): void
    {
        $query = SchoolClass::query()
            ->where('area_id', $season->area_id)
            ->whereIn('id', $classIds);

        if (! $user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        }

        $season->classes()->sync($query->pluck('id')->all());
    }

    private function authorizeSeason(Request $request, Season $season): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $season->created_by === $user->id, 403);
    }

    private function authorizeClass(Request $request, SchoolClass $schoolClass): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $schoolClass->teacher_id === $user->id, 403);
    }
}
