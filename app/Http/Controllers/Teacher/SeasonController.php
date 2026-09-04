<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeasonController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $seasons = Season::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('created_by', $user->id))
            ->with('area')
            ->withCount('classes')
            ->latest()
            ->get();

        return view('teacher.seasons.index', compact('seasons'));
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

    /**
     * @param  list<int>  $selectedClassIds
     * @return array{season: Season, areas: Collection, classes: Collection, selectedClassIds: list<int>}
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

        return compact('season', 'areas', 'classes', 'selectedClassIds');
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
}
