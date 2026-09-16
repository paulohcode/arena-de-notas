<?php

namespace App\Http\Controllers\Teacher;

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

    public function show(SchoolClass $schoolClass): View
    {
        $this->authorize('manage', $schoolClass);

        $inventory = $this->pets->inventory($schoolClass);

        return view('teacher.pets', [
            'class' => $schoolClass,
            'catalog' => $inventory['catalog'],
            'owners' => $inventory['owners'],
            'listingsCount' => $inventory['listings_count'],
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
        ]);
    }

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(PetCatalog::itemRules(), PetCatalog::itemMessages());
        $pet = $this->pets->createItem($data, $schoolClass, $request->file('gif'));

        return redirect()
            ->route('teacher.pets.show', $schoolClass)
            ->with('success', $pet->name.' cadastrado na loja de mascotes.');
    }

    public function edit(SchoolClass $schoolClass, Pet $pet): View
    {
        $this->authorize('manage', $schoolClass);

        return view('teacher.pet-edit', [
            'class' => $schoolClass,
            'pet' => $pet,
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass, Pet $pet): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(PetCatalog::itemUpdateRules(), PetCatalog::itemMessages());
        $data['active'] = $request->boolean('active', true);
        $updated = $this->pets->updateItem($pet, $data, $request->file('gif'));

        return redirect()
            ->route('teacher.pets.show', $schoolClass)
            ->with('success', $updated->name.' atualizado.');
    }

    public function restock(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $pet = $this->pets->restock($schoolClass, (int) $data['pet_id'], (int) $data['quantity']);

        return redirect()
            ->route('teacher.pets.show', $schoolClass)
            ->with('success', 'Estoque de '.$pet->name.' atualizado.');
    }
}
