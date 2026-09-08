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
        ]);
    }

    public function restock(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'item' => ['required', 'string', Rule::in(array_keys(CosmeticCatalog::ITEMS))],
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->shop->restock($schoolClass, $data['item'], $data['quantity']);

        $item = CosmeticCatalog::item($data['item']);

        return redirect()
            ->route('teacher.shop.show', $schoolClass)
            ->with('success', ($item['name'] ?? 'Item').' atualizado: '.$data['quantity'].' à venda na loja.');
    }
}
