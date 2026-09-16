<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\SchoolClass;
use App\Services\PetShopService;
use App\Support\PetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'customPets' => Pet::query()
                ->with('schoolClass')
                ->whereNull('species_key')
                ->orderByDesc('id')
                ->limit(40)
                ->get(),
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(PetCatalog::itemRules(), PetCatalog::itemMessages());
        $created = $this->pets->createItemForAllClasses($data, $request->file('gif'));

        $count = $created->count();
        $name = $data['name'];

        return redirect()
            ->route('admin.pets.index')
            ->with('success', $count === 0
                ? 'Nenhuma turma para receber o mascote.'
                : "{$name} criado em {$count} turma(s).");
    }

    public function edit(Pet $pet): View
    {
        return view('admin.pets.edit', [
            'pet' => $pet,
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
        ]);
    }

    public function update(Request $request, Pet $pet): RedirectResponse
    {
        $data = $request->validate(PetCatalog::itemUpdateRules(), PetCatalog::itemMessages());
        $data['active'] = $request->boolean('active', true);
        $updated = $this->pets->updateItem($pet, $data, $request->file('gif'));

        return redirect()
            ->route('admin.pets.index')
            ->with('success', $updated->name.' atualizado.');
    }
}
