<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\PetShopService;
use App\Support\PetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PetController extends Controller
{
    public function __construct(private PetShopService $pets) {}

    public function show(Request $request, SchoolClass $schoolClass): View
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
            'scopeClasses' => $this->scopeClasses($request->user()),
        ]);
    }

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(PetCatalog::itemStoreRules(), PetCatalog::itemMessages());
        $gif = $request->file('gif');

        if ($data['scope'] === 'all') {
            $created = $this->pets->createItemForClasses(
                $data,
                $this->scopeClasses($request->user()),
                $gif,
            );
            $count = $created->count();
            $name = $data['name'];

            return redirect()
                ->route('teacher.pets.show', $schoolClass)
                ->with('success', $count === 0
                    ? 'Nenhuma turma para receber o mascote.'
                    : $name.' cadastrado em '.$count.' turma(s).');
        }

        $target = SchoolClass::query()->findOrFail((int) $data['class_id']);
        $this->authorize('manage', $target);
        $pet = $this->pets->createItem($data, $target, $gif);

        return redirect()
            ->route('teacher.pets.show', $target)
            ->with('success', $pet->name.' cadastrado na loja de mascotes.');
    }

    public function edit(Request $request, SchoolClass $schoolClass, Pet $pet): View
    {
        $this->authorize('manage', $schoolClass);

        return view('teacher.pet-edit', [
            'class' => $schoolClass,
            'pet' => $pet,
            'rarities' => PetCatalog::RARITIES,
            'spriteKeys' => PetCatalog::spriteKeys(),
            'scopeClasses' => $this->scopeClasses($request->user()),
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass, Pet $pet): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(PetCatalog::itemUpdateRules(), PetCatalog::itemMessages());
        $data['active'] = $request->boolean('active', true);
        $gif = $request->file('gif');

        if ($data['scope'] === 'all') {
            $updated = $this->pets->updateItemForClasses(
                $pet,
                $data,
                $this->scopeClasses($request->user()),
                $gif,
            );
            $count = $updated->count();
            $name = $updated->first()?->name ?? $data['name'];

            return redirect()
                ->route('teacher.pets.show', $schoolClass)
                ->with('success', $name.' atualizado em '.$count.' turma(s).');
        }

        $updated = $this->pets->updateItem($pet, $data, $gif);

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

    /**
     * @return Collection<int, SchoolClass>
     */
    private function scopeClasses(User $user): Collection
    {
        return ($user->isAdmin()
            ? SchoolClass::query()
            : $user->taughtClasses()
        )
            ->with('area')
            ->orderBy('name')
            ->get();
    }
}
