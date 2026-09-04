<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function index(): View
    {
        $areas = Area::query()->withCount(['teachers', 'classes'])->orderBy('name')->get();

        return view('admin.areas.index', compact('areas'));
    }

    public function create(): View
    {
        return view('admin.areas.form', [
            'area' => new Area([
                'color' => '#c2410c',
                'emblem' => 'castle',
                'map_x' => 50,
                'map_y' => 50,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $area = Area::query()->create($this->validated($request));

        return redirect()->route('admin.areas.index')
            ->with('success', "Reino \"{$area->name}\" criado.");
    }

    public function edit(Area $area): View
    {
        return view('admin.areas.form', compact('area'));
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $area->update($this->validated($request, $area));

        return redirect()->route('admin.areas.index')
            ->with('success', "Reino \"{$area->name}\" atualizado.");
    }

    public function position(Request $request, Area $area): JsonResponse
    {
        $data = $request->validate([
            'map_x' => ['required', 'numeric', 'min:5', 'max:95'],
            'map_y' => ['required', 'numeric', 'min:5', 'max:95'],
        ]);

        $area->update([
            'map_x' => (int) round((float) $data['map_x']),
            'map_y' => (int) round((float) $data['map_y']),
        ]);

        return response()->json([
            'map_x' => $area->map_x,
            'map_y' => $area->map_y,
        ]);
    }

    public function destroy(Area $area): RedirectResponse
    {
        if ($area->classes()->exists()) {
            return back()->withErrors(['area' => 'Não é possível excluir um reino que ainda possui turmas.']);
        }

        $area->teachers()->detach();
        $area->delete();

        return redirect()->route('admin.areas.index')
            ->with('success', 'Reino removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Area $area = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('areas', 'slug')->ignore($area?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['required', 'string', 'max:20'],
            'emblem' => ['required', 'string', Rule::in(array_keys(Area::EMBLEMS))],
            'map_x' => ['required', 'integer', 'min:5', 'max:95'],
            'map_y' => ['required', 'integer', 'min:5', 'max:95'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'])
            : Str::slug($data['name']);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
