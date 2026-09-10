<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\CosmeticShopService;
use App\Support\CosmeticCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private CosmeticShopService $shop) {}

    public function show(SchoolClass $schoolClass): View
    {
        $this->authorize('manage', $schoolClass);

        $inventory = $this->shop->inventory($schoolClass);

        return view('teacher.shop', [
            'class' => $schoolClass,
            'catalog' => $inventory['catalog'],
            'slots' => CosmeticCatalog::SLOTS,
            'rarities' => CosmeticCatalog::RARITIES,
            'currencies' => CosmeticCatalog::CURRENCIES,
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(CosmeticCatalog::itemRules(), CosmeticCatalog::itemMessages());
        $item = $this->shop->createItem($data, $schoolClass);

        return redirect()
            ->route('teacher.shop.show', $schoolClass)
            ->with('success', $item->name.' cadastrado na loja desta turma.');
    }

    public function restock(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'item' => ['required', 'string', Rule::in(CosmeticCatalog::keysForClass($schoolClass))],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->shop->restock($schoolClass, $data['item'], $data['quantity']);

        $item = CosmeticCatalog::item($data['item']);

        return redirect()
            ->route('teacher.shop.show', $schoolClass)
            ->with('success', ($item['name'] ?? 'Item').' atualizado: '.$data['quantity'].' à venda na loja.');
    }
}
