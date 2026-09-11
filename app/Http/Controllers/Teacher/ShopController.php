<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\ShopItem;
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
            'currencies' => CosmeticCatalog::currencies(),
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(CosmeticCatalog::itemRules(), CosmeticCatalog::itemMessages());
        $item = $this->shop->createItem($data, $schoolClass);
        $success = $item->usesAuras()
            ? $item->name.' cadastrado na loja do reino (único para todas as turmas).'
            : $item->name.' cadastrado na loja desta turma.';

        return redirect()
            ->route('teacher.shop.show', $schoolClass)
            ->with('success', $success);
    }

    public function edit(SchoolClass $schoolClass, ShopItem $shopItem): View
    {
        $this->authorize('manage', $schoolClass);

        return view('teacher.shop-item-edit', [
            'class' => $schoolClass,
            'item' => $shopItem,
            'slots' => CosmeticCatalog::SLOTS,
            'rarities' => CosmeticCatalog::RARITIES,
            'currencies' => CosmeticCatalog::currencies(),
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function update(Request $request, SchoolClass $schoolClass, ShopItem $shopItem): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate(CosmeticCatalog::itemUpdateRules(), CosmeticCatalog::itemMessages());
        $item = $this->shop->updateItem($shopItem, $data);

        return redirect()
            ->route('teacher.shop.show', $schoolClass)
            ->with('success', $item->name.' atualizado.');
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
