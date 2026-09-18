<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\SchoolClass;
use App\Services\PetShopService;
use App\Support\PetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PetController extends Controller
{
    public function __construct(private PetShopService $pets) {}

    public function index(): View
    {
        $classes = SchoolClass::query()
            ->with(['area', 'teacher'])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('admin.pets.index', [
            'classes' => $classes,
            'overview' => $this->pets->overviewForClasses($classes),
            'customPets' => $this->recentCustomPets(),
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
            'scopeClasses' => $classes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(PetCatalog::itemStoreRules(), PetCatalog::itemMessages());
        $gif = $request->file('gif');

        if ($data['scope'] === 'one') {
            $class = SchoolClass::query()->findOrFail((int) $data['class_id']);
            $pet = $this->pets->createItem($data, $class, $gif);

            return redirect()
                ->route('admin.pets.index')
                ->with('success', $pet->name.' cadastrado em '.$class->name.'.');
        }

        $created = $this->pets->createItemForAllClasses($data, $gif);
        $count = $created->count();
        $name = $data['name'];

        return redirect()
            ->route('admin.pets.index')
            ->with('success', $count === 0
                ? 'Nenhuma turma para receber o mascote.'
                : $name.' cadastrado em '.$count.' turma(s).');
    }

    public function edit(Pet $pet): View
    {
        $pet->loadMissing('schoolClass');

        return view('admin.pets.edit', [
            'pet' => $pet,
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
            'scopeClasses' => SchoolClass::query()
                ->with('area')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Pet $pet): RedirectResponse
    {
        $data = $request->validate(PetCatalog::itemUpdateRules(), PetCatalog::itemMessages());
        $data['active'] = $request->boolean('active', true);
        $gif = $request->file('gif');

        if ($data['scope'] === 'all') {
            $updated = $this->pets->updateItemForClasses(
                $pet,
                $data,
                SchoolClass::query()->orderBy('id')->get(),
                $gif,
            );
            $count = $updated->count();
            $name = $updated->first()?->name ?? $data['name'];

            return redirect()
                ->route('admin.pets.index')
                ->with('success', $name.' atualizado em '.$count.' turma(s).');
        }

        $updated = $this->pets->updateItem($pet, $data, $gif);

        return redirect()
            ->route('admin.pets.index')
            ->with('success', $updated->name.' atualizado.');
    }

    /**
     * @return Collection<int, array{pet: Pet, count: int, classes: Collection<int, string>}>
     */
    private function recentCustomPets(): Collection
    {
        return Pet::query()
            ->with('schoolClass')
            ->whereNull('species_key')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->groupBy('name')
            ->map(function (Collection $copies): array {
                $pet = $copies->first();

                return [
                    'pet' => $pet,
                    'count' => $copies->count(),
                    'classes' => $copies
                        ->map(fn (Pet $copy): ?string => $copy->schoolClass?->name)
                        ->filter()
                        ->unique()
                        ->values(),
                ];
            })
            ->take(12)
            ->values();
    }
}
